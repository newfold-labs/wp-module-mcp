import { writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import * as core from '@actions/core';
import { runAgentEval } from './agent-loop.js';
import { connectMcpClient } from './mcp-client.js';
import { fetchOpenAiToolsFromList } from './mcp-tools.js';
import type { TestCase } from './coverage.js';

const EVAL_DELAY_MS = 500;

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

    for (let i = 0; i < testCases.length; i++) {
      const test = testCases[i];

      if (!options.runAll && options.changedSources.length > 0) {
        if (!options.changedSources.some((s) => s === test.source)) {
          skip++;
          continue;
        }
      }

      const result = await runAgentEval({
        client,
        prompt: test.prompt,
        expectedTool: test.expected_tool,
        model: options.model,
        gatewayUrl: options.gatewayUrl,
        gatewayToken: options.gatewayToken,
      });

      if (i === 0) {
        console.log('::group::Debug: First eval');
        console.log(`Turns: ${result.turns}, matched: ${result.matched}, actual: ${result.actualTool}`);
        if (result.error) {
          console.log(`Error: ${result.error}`);
        }
        console.log('::endgroup::');
      }

      if (result.error && !result.matched) {
        rows.push(
          `| :warning: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | ${escapeCell(result.error)} |`
        );
        error++;
      } else if (result.matched) {
        rows.push(
          `| :white_check_mark: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | \`${result.actualTool}\` (${result.turns} turns) |`
        );
        pass++;
      } else {
        rows.push(
          `| :x: | ${escapeCell(test.description)} | \`${test.expected_tool}\` | \`${result.actualTool}\` (${result.turns} turns) |`
        );
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
