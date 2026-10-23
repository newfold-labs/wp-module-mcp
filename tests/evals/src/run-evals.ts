import { writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import * as core from '@actions/core';
import { runAgentEval, type AgentEvalResult } from './agent-loop.js';
import { connectMcpClient } from './mcp-client.js';
import { fetchOpenAiToolsFromList } from './mcp-tools.js';
import { classifyEvalResult } from './eval-status.js';
import { safeTrace, summarizeFailure } from './diagnostics.js';
import { MenuOutcomeFixture } from './menu-outcome.js';
import { resolveEvalPrompt } from './eval-prompt.js';
import { blockingPrerequisite } from './eval-dependencies.js';
import { pageHasImage, pageImageReplaced, productHasTerms, WordPressOutcomes } from './wp-outcomes.js';
import { resolveMcpAuthorization } from './auth.js';
import type { TestCase } from './coverage.js';

const EVAL_DELAY_MS = 500;

type EvalUnit =
  | { kind: 'single'; test: TestCase }
  | { kind: 'series'; seriesId: string; tests: TestCase[] };

export interface EvalRunOptions {
  repoRoot: string;
  model: string;
  gatewayUrl: string;
  gatewayToken: string;
  runAll: boolean;
  changedSources: string[];
}

export interface EvalRunResult {
  pass: number;
  fail: number;
  warning: number;
  error: number;
  skip: number;
  blocked: number;
  passRate: number;
  toolCount: number;
  reportMarkdown: string;
  diagnostics: Array<Record<string, unknown>>;
}

export async function runEvals(options: EvalRunOptions): Promise<EvalRunResult> {
  const testCases = await loadTestCases(options.repoRoot);
  const client = await connectMcpClient();

  let toolCount = 0;
  try {
    const tools = await fetchOpenAiToolsFromList(client);
    const standaloneIds = process.env.WP_EVAL_IDS ? JSON.parse(process.env.WP_EVAL_IDS) as Record<string, number> : {};
    const serverUrl = process.env.MCP_EVAL_SERVER_URL ?? 'http://localhost:8888/index.php?rest_route=/blu/mcp';
    // Same credentials as the MCP client, including MCP_EVAL_AUTH_BASIC-only configurations.
    const { authorization } = resolveMcpAuthorization();
    const wpOutcomes = new WordPressOutcomes(serverUrl, authorization);
    toolCount = tools.length;
    console.log(`Using ${toolCount} MCP tool(s) from tools/list`);

    let pass = 0;
    let fail = 0;
    let warning = 0;
    let error = 0;
    let skip = 0;
    let blocked = 0;
    const statuses = new Map<string, string>();
    const rows: string[] = [];
    const diagnostics: Array<Record<string, unknown>> = [];

    const units = groupTestCases(testCases);
    let firstEvalLogged = false;

    for (const unit of units) {
      let runnable: TestCase[] = [];
      if (unit.kind === 'single') {
        const shouldRun = shouldRunTest(unit.test, options);
        runnable = shouldRun ? [unit.test] : [];
        if (!shouldRun) {
          skip += 1;
        }
      } else {
        // Shared-context series must run as a whole if any step is selected.
        const shouldRunSeries = unit.tests.some((test) => shouldRunTest(test, options));
        runnable = shouldRunSeries ? unit.tests : [];
        if (!shouldRunSeries) {
          skip += unit.tests.length;
        }
      }
      if (runnable.length === 0) {
        continue;
      }

      if (unit.kind === 'series') {
        console.log(
          `::group::Series ${unit.seriesId} (${runnable.length} step(s), shared context)`
        );
      }

      let conversationMessages: AgentEvalResult['conversationMessages'] = [];
      let menuFixture: MenuOutcomeFixture | undefined;
      let fixtureError: string | undefined;
      if (unit.kind === 'series' && unit.seriesId === 'menu-primary') {
        try {
          menuFixture = await MenuOutcomeFixture.create(serverUrl, authorization);
        } catch (err) {
          // Report the series as errors instead of aborting the whole run.
          fixtureError = `Fixture setup failed: ${err instanceof Error ? err.message : String(err)}`;
        }
      }

      try {
        for (let stepIndex = 0; stepIndex < runnable.length; stepIndex++) {
          const test = runnable[stepIndex];
          const inSeries = unit.kind === 'series';
          const stepLabel = inSeries
            ? `[${unit.seriesId} ${stepIndex + 1}/${runnable.length}] `
            : '';

          const prerequisite = blockingPrerequisite(test.requires_success_of, statuses);
          if (prerequisite) {
            blocked++;
            statuses.set(test.id, 'blocked');
            rows.push(`| :no_entry_sign: | ${escapeCell(stepLabel + test.description)} | \`${test.expected_tool}\` | Blocked by ${escapeCell(prerequisite)} |`);
            diagnostics.push({ id: test.id, status: 'blocked', prerequisite });
            continue;
          }

          if (fixtureError) {
            error++;
            statuses.set(test.id, 'error');
            rows.push(`| :boom: | ${escapeCell(stepLabel + test.description)} | ${test.expected_outcome?.replaceAll('_', ' ') ?? `\`${test.expected_tool}\``} | Eval error (details in trace artifact) |`);
            diagnostics.push({ id: test.id, status: 'error', error: fixtureError });
            continue;
          }

          const prompt = menuFixture
            ? test.expected_outcome === 'menu_page_present'
              ? `Add the published page "AI Eval Landing Page" to the "AI Eval Primary ${menuFixture.pageId}" navigation menu.`
              : `Remove the "AI Eval Landing Page" page link from the "AI Eval Primary ${menuFixture.pageId}" navigation menu.`
            : resolveEvalPrompt(test.id, test.prompt, {
                productId: Number(process.env.WP_EVAL_PRODUCT_ID) || undefined,
                portfolioId: Number(process.env.WP_EVAL_PORTFOLIO_ID) || undefined,
                testimonialId: Number(process.env.WP_EVAL_TESTIMONIAL_ID) || undefined,
                pageId: Number(process.env.WP_EVAL_PAGE_ID) || undefined,
                ids: standaloneIds,
              });
          const pageId = ['inserting-media-into-existing-page-32', 'replacing-media-inside-page-33'].includes(test.id)
            ? Number(process.env.WP_EVAL_PAGE_ID) || undefined : undefined;
          let priorPageContent = '';
          const outcomeCheck = menuFixture
            ? async () => (await menuFixture.containsPage()) === (test.expected_outcome === 'menu_page_present')
            : test.expected_outcome === 'page_image_present'
              ? async () => pageHasImage(await wpOutcomes.pageContent(pageId))
              : test.expected_outcome === 'page_image_changed'
                ? async () => pageImageReplaced(priorPageContent, await wpOutcomes.pageContent(pageId))
                : test.expected_outcome === 'page_published'
                  ? async () => (await wpOutcomes.pageStatus(pageId)) === 'publish'
                : test.expected_outcome === 'product_terms'
                  ? async () => productHasTerms(await wpOutcomes.product() ?? {}, 'AI Tools', 'Automation')
                  : test.expected_outcome === 'product_published'
                    ? async () => (await wpOutcomes.product())?.status === 'publish'
                    : test.expected_outcome === 'product_absent'
                      ? async () => !(await wpOutcomes.product())
                      : undefined;

          let result: AgentEvalResult;
          const previousMessageCount = inSeries ? conversationMessages.length : 0;
          try {
            // Fixture reads run here so a REST failure is recorded as this case's error.
            if (menuFixture && test.expected_outcome === 'menu_page_absent') {
              await menuFixture.ensurePageItem();
            }
            if (test.expected_outcome === 'page_image_changed') {
              priorPageContent = await wpOutcomes.pageContent(pageId);
            }
            result = await runAgentEval({
              client,
              tools,
              prompt,
              outcomeCheck,
              expectedTool: test.expected_tool,
              expectToolError: test.expect_tool_error,
              restAlternatives: test.rest_alternatives,
              model: options.model,
              gatewayUrl: options.gatewayUrl,
              gatewayToken: options.gatewayToken,
              conversationMessages: inSeries ? conversationMessages : undefined,
            });
          } catch (err) {
            const msg = err instanceof Error ? err.message : String(err);
            result = {
              matched: false,
              actualTool: '(none)',
              error: `Unhandled eval error: ${msg}`,
              turns: 1,
              conversationMessages: inSeries ? conversationMessages : [],
            };
          }

          conversationMessages = result.conversationMessages;

          if (!firstEvalLogged) {
            firstEvalLogged = true;
            console.log('::group::Debug: First eval');
            console.log(
              `Turns: ${result.turns}, matched: ${result.matched}, actual: ${compactTool(result.actualTool)}`
            );
            if (result.error) {
              console.log(`Error: ${result.error}`);
            }
            console.log('::endgroup::');
          }

          const description = stepLabel + test.description;
          const status = classifyEvalResult(result);
          statuses.set(test.id, status);
          const currentMessages = result.conversationMessages.slice(previousMessageCount);
          const reason = result.error ? 'Eval error' : summarizeFailure(currentMessages);
          const actual = compactTool(result.actualTool);
          const expected = test.expected_outcome
            ? test.expected_outcome.replaceAll('_', ' ')
            : `\`${test.expected_tool}\``;
          diagnostics.push({ id: test.id, status, selectionMatched: result.matched, executionFailed: result.executionFailed ?? false, reason: status === 'pass' ? undefined : reason, error: result.error, turns: result.turns, trace: safeTrace(currentMessages) });
          if (status === 'error') {
            rows.push(
              `| :boom: | ${escapeCell(description)} | ${expected} | Eval error (details in trace artifact) |`
            );
            error++;
          } else if (status === 'pass') {
            rows.push(
              `| :white_check_mark: | ${escapeCell(description)} | ${expected} | \`${actual}\` (${result.turns} turns) |`
            );
            pass++;
          } else if (status === 'warning') {
            rows.push(
              `| :warning: | ${escapeCell(description)} | ${expected} | ${result.executionFailed ? 'Selected; execution failed' : 'REST alternative'}: \`${actual}\` (${result.turns} turns) |`
            );
            warning++;
          } else {
            rows.push(
              `| :x: | ${escapeCell(description)} | ${expected} | ${escapeCell(reason)}; \`${actual}\` (${result.turns} turns) |`
            );
            fail++;
          }

          if (inSeries) {
            console.log(
              `  step ${stepIndex + 1}/${runnable.length}: ${test.description} → ${
                status
              } (${actual})`
            );
          }

          await sleep(EVAL_DELAY_MS);
        }
      } finally {
        if (menuFixture) {
          await menuFixture.cleanup().catch((err: unknown) => {
            console.warn(`Menu fixture cleanup failed: ${err instanceof Error ? err.message : String(err)}`);
          });
        }
      }

      if (unit.kind === 'series') {
        console.log('::endgroup::');
      }
    }

    const totalRun = pass + warning + fail + error;
    const passRate = totalRun > 0 ? Math.floor((pass * 100) / totalRun) : 0;
    const statusEmoji = fail > 0 || error > 0 ? ':red_circle:' : warning > 0 ? ':yellow_circle:' : ':green_circle:';

    const lines = [
      `## ${statusEmoji} MCP AI Eval Results`,
      '',
      `**Model:** \`${options.model}\` | **MCP tools (tools/list):** ${toolCount} | **Passed:** ${pass} | **Warnings:** ${warning} | **Failed:** ${fail} | **Errors:** ${error} | **Blocked:** ${blocked} | **Skipped:** ${skip} | **Pass Rate:** ${passRate}%`,
      'Detailed traces: `mcp-ai-eval-trace` artifact on this workflow run.',
      '',
      '| Status | Test | Expected Tool / Outcome | Actual / Notes |',
      '|--------|------|---------------|----------------|',
      ...rows,
    ];

    if (skip > 0) {
      lines.push(
        '',
        `<details><summary>${skip} test(s) skipped (unchanged source files)</summary>`,
        '',
        'Only tests for changed ability files were run. Use `workflow_dispatch` with `run_all: true` to run the full suite.',
        '</details>'
      );
    }

    const reportMarkdown = lines.join('\n');

    if (process.env.GITHUB_OUTPUT) {
      core.setOutput('pass', String(pass));
      core.setOutput('fail', String(fail));
      core.setOutput('warning', String(warning));
      core.setOutput('error', String(error));
      core.setOutput('pass_rate', String(passRate));
      core.setOutput('tool_count', String(toolCount));
    }

    return { pass, fail, warning, error, skip, blocked, passRate, toolCount, reportMarkdown, diagnostics };
  } finally {
    await client.close();
  }
}

function groupTestCases(cases: TestCase[]): EvalUnit[] {
  const units: EvalUnit[] = [];
  let index = 0;

  while (index < cases.length) {
    const current = cases[index];
    if (!current.series_id) {
      units.push({ kind: 'single', test: current });
      index++;
      continue;
    }

    const seriesId = current.series_id;
    const seriesTests: TestCase[] = [];
    while (index < cases.length && cases[index].series_id === seriesId) {
      seriesTests.push(cases[index]);
      index++;
    }
    units.push({ kind: 'series', seriesId, tests: seriesTests });
  }

  return units;
}

function shouldRunTest(test: TestCase, options: EvalRunOptions): boolean {
  if (options.runAll || options.changedSources.length === 0) {
    return true;
  }
  return options.changedSources.some((source) => source === test.source);
}

async function loadTestCases(repoRoot: string): Promise<TestCase[]> {
  const { readFile } = await import('node:fs/promises');
  return JSON.parse(
    await readFile(join(repoRoot, 'tests/evals/test-cases.json'), 'utf8')
  ) as TestCase[];
}

function escapeCell(value: string): string {
  return value.replace(/\|/g, '\\|').replace(/\n/g, ' ');
}

function compactTool(value: string): string {
  const name = value.split('(')[0];
  const ability = value.match(/"ability_name"\s*:\s*"([a-zA-Z0-9_/-]+)"/);
  return ability ? `${name} → ${ability[1]}` : name;
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export async function writeEvalReport(reportPath: string, markdown: string): Promise<void> {
  await writeFile(reportPath, markdown, 'utf8');
}
