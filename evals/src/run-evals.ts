import { readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import * as core from '@actions/core';
import type { OpenAiFunctionTool } from './gateway.js';
import { normalizeToolName } from './tool-names.js';
import { sanitizeParametersForOpenAi } from './sanitize-openai-schema.js';
import type { TestCase } from './coverage.js';

const EVAL_DELAY_MS = 500;

interface ChatCompletionResponse {
  choices?: Array<{
    message?: {
      tool_calls?: Array<{
        function?: { name?: string };
      }>;
    };
    finish_reason?: string;
  }>;
  error?: { message?: string };
  model?: string;
}

export interface EvalRunOptions {
  repoRoot: string;
  toolsFile: string;
  model: string;
  gatewayUrl: string;
  gatewayToken: string;
  runAll: boolean;
  changedSources: string[];
  toolCount: number;
}

export interface EvalRunResult {
  pass: number;
  fail: number;
  error: number;
  skip: number;
  passRate: number;
  reportMarkdown: string;
}

export async function runEvals(options: EvalRunOptions): Promise<EvalRunResult> {
  const rawTools = JSON.parse(await readFile(options.toolsFile, 'utf8')) as OpenAiFunctionTool[];
  const tools = rawTools.map((tool) => ({
    ...tool,
    function: {
      ...tool.function,
      parameters: sanitizeParametersForOpenAi(tool.function.parameters, tool.function.name),
    },
  }));
  const testCases = JSON.parse(
    await readFile(join(options.repoRoot, 'evals/test-cases.json'), 'utf8')
  ) as TestCase[];

  let pass = 0;
  let fail = 0;
  let error = 0;
  let skip = 0;
  const rows: string[] = [];

  for (let i = 0; i < testCases.length; i++) {
    const test = testCases[i];
    const expectedNorm = normalizeToolName(test.expected_tool);

    if (!options.runAll && options.changedSources.length > 0) {
      const sourceBase = test.source;
      if (!options.changedSources.some((s) => s === sourceBase)) {
        skip++;
        continue;
      }
    }

    const body = {
      model: options.model,
      messages: [{ role: 'user', content: test.prompt }],
      tools,
      tool_choice: 'auto' as const,
    };

    let httpCode = 0;
    let responseText = '';

    try {
      const response = await fetch(options.gatewayUrl, {
        method: 'POST',
        headers: {
          'cf-aig-authorization': `Bearer ${options.gatewayToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
        signal: AbortSignal.timeout(30_000),
      });
      httpCode = response.status;
      responseText = await response.text();
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      rows.push(`| :warning: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | Request failed: ${escapeCell(msg)} |`);
      error++;
      await sleep(EVAL_DELAY_MS);
      continue;
    }

    if (i === 0) {
      console.log('::group::Debug: First API call');
      console.log(`HTTP status: ${httpCode}`);
      console.log(`MCP tools loaded: ${tools.length}`);
      console.log(`Response (first 500 chars): ${responseText.slice(0, 500)}`);
      console.log('::endgroup::');
    }

    if (httpCode < 200 || httpCode >= 300) {
      rows.push(
        `| :warning: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | HTTP ${httpCode}: ${escapeCell(responseText.slice(0, 100))} |`
      );
      error++;
      await sleep(EVAL_DELAY_MS);
      continue;
    }

    let parsed: ChatCompletionResponse;
    try {
      parsed = JSON.parse(responseText) as ChatCompletionResponse;
    } catch {
      rows.push(
        `| :warning: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | Invalid JSON response |`
      );
      error++;
      await sleep(EVAL_DELAY_MS);
      continue;
    }

    if (parsed.error?.message) {
      rows.push(
        `| :warning: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | API error: ${escapeCell(parsed.error.message)} |`
      );
      error++;
      await sleep(EVAL_DELAY_MS);
      continue;
    }

    const calledTool = parsed.choices?.[0]?.message?.tool_calls?.[0]?.function?.name ?? '';
    const calledNorm = normalizeToolName(calledTool);

    if (!calledTool) {
      rows.push(`| :x: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | No tool called |`);
      fail++;
    } else if (calledNorm === expectedNorm) {
      rows.push(
        `| :white_check_mark: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | \`${calledTool}\` |`
      );
      pass++;
    } else {
      rows.push(`| :x: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | \`${calledTool}\` |`);
      fail++;
    }

    await sleep(EVAL_DELAY_MS);
  }

  const totalRun = pass + fail + error;
  const passRate = totalRun > 0 ? Math.floor((pass * 100) / totalRun) : 0;
  const statusEmoji = fail > 0 || error > 0 ? ':red_circle:' : ':green_circle:';

  const lines = [
    `## ${statusEmoji} MCP AI Eval Results`,
    '',
    `**Model:** \`${options.model}\` | **MCP tools:** ${options.toolCount} (live) | **Passed:** ${pass} | **Failed:** ${fail} | **Errors:** ${error} | **Skipped:** ${skip} | **Pass Rate:** ${passRate}%`,
    '',
    '| Status | Test | Expected Tool | Actual Tool |',
    '|--------|------|---------------|-------------|',
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
  }

  return { pass, fail, error, skip, passRate, reportMarkdown };
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
