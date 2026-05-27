import { writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import * as core from '@actions/core';
import { runAgentEval, type AgentEvalResult } from './agent-loop.js';
import { connectMcpClient } from './mcp-client.js';
import { fetchOpenAiToolsFromList } from './mcp-tools.js';
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
  error: number;
  skip: number;
  passRate: number;
  toolCount: number;
  reportMarkdown: string;
}

export async function runEvals(options: EvalRunOptions): Promise<EvalRunResult> {
  const testCases = await loadTestCases(options.repoRoot);
  const client = await connectMcpClient();

  let toolCount = 0;
  try {
    const tools = await fetchOpenAiToolsFromList(client);
    toolCount = tools.length;
    console.log(`Using ${toolCount} MCP tool(s) from tools/list`);

    let pass = 0;
    let fail = 0;
    let error = 0;
    let skip = 0;
    const rows: string[] = [];

    const units = groupTestCases(testCases);
    let firstEvalLogged = false;

    for (const unit of units) {
      const tests =
        unit.kind === 'single' ? [unit.test] : unit.tests;

      const runnable = tests.filter((test) => shouldRunTest(test, options));
      skip += tests.length - runnable.length;
      if (runnable.length === 0) {
        continue;
      }

      if (unit.kind === 'series') {
        console.log(
          `::group::Series ${unit.seriesId} (${runnable.length} step(s), shared context)`
        );
      }

      let conversationMessages: AgentEvalResult['conversationMessages'] = [];

      for (let stepIndex = 0; stepIndex < runnable.length; stepIndex++) {
        const test = runnable[stepIndex];
        const inSeries = unit.kind === 'series';
        const stepLabel = inSeries
          ? `[${unit.seriesId} ${stepIndex + 1}/${runnable.length}] `
          : '';

        let result: AgentEvalResult;
        try {
          result = await runAgentEval({
            client,
            prompt: test.prompt,
            expectedTool: test.expected_tool,
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
            `Turns: ${result.turns}, matched: ${result.matched}, actual: ${result.actualTool}`
          );
          if (result.error) {
            console.log(`Error: ${result.error}`);
          }
          console.log('::endgroup::');
        }

        const description = stepLabel + test.description;
        if (result.error && !result.matched) {
          rows.push(
            `| :warning: | ${escapeCell(description)} | \`${test.expected_tool}\` | ${escapeCell(result.error)} |`
          );
          error++;
        } else if (result.matched) {
          rows.push(
            `| :white_check_mark: | ${escapeCell(description)} | \`${test.expected_tool}\` | \`${result.actualTool}\` (${result.turns} turns) |`
          );
          pass++;
        } else {
          rows.push(
            `| :x: | ${escapeCell(description)} | \`${test.expected_tool}\` | \`${result.actualTool}\` (${result.turns} turns) |`
          );
          fail++;
        }

        if (inSeries) {
          console.log(
            `  step ${stepIndex + 1}/${runnable.length}: ${test.description} → ${
              result.matched ? 'pass' : result.error ? 'error' : 'fail'
            } (${result.actualTool})`
          );
        }

        await sleep(EVAL_DELAY_MS);
      }

      if (unit.kind === 'series') {
        console.log('::endgroup::');
      }
    }

    const totalRun = pass + fail + error;
    const passRate = totalRun > 0 ? Math.floor((pass * 100) / totalRun) : 0;
    const statusEmoji = fail > 0 || error > 0 ? ':red_circle:' : ':green_circle:';

    const lines = [
      `## ${statusEmoji} MCP AI Eval Results`,
      '',
      `**Model:** \`${options.model}\` | **MCP tools (tools/list):** ${toolCount} | **Passed:** ${pass} | **Failed:** ${fail} | **Errors:** ${error} | **Skipped:** ${skip} | **Pass Rate:** ${passRate}%`,
      '',
      '| Status | Test | Expected Tool | Actual / Notes |',
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
      core.setOutput('error', String(error));
      core.setOutput('pass_rate', String(passRate));
      core.setOutput('tool_count', String(toolCount));
    }

    return { pass, fail, error, skip, passRate, toolCount, reportMarkdown };
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
    await readFile(join(repoRoot, 'evals/test-cases.json'), 'utf8')
  ) as TestCase[];
}

function escapeCell(value: string): string {
  return value.replace(/\|/g, '\\|').replace(/\n/g, ' ');
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export async function writeEvalReport(reportPath: string, markdown: string): Promise<void> {
  await writeFile(reportPath, markdown, 'utf8');
}
