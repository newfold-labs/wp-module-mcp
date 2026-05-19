#!/usr/bin/env node
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as core from '@actions/core';
import { assertMcpAuthConfigured } from './auth.js';
import { connectMcpClient } from './mcp-client.js';
import { fetchOpenAiToolsFromMcp } from './gateway.js';
import { runCoverageCheck, writeCoverageReport } from './coverage.js';
import { runEvals, writeEvalReport } from './run-evals.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(__dirname, '../..');

/** CI sets MCP_TOOL_COUNT; locally count lines from the last fetch-tools output. */
async function resolveToolCount(): Promise<number> {
  const fromEnv = process.env.MCP_TOOL_COUNT;
  if (fromEnv !== undefined && fromEnv !== '') {
    return Number(fromEnv);
  }

  const namesFile = process.env.MCP_ABILITY_NAMES_OUT ?? '/tmp/mcp-ability-names.txt';
  try {
    const count = (await readFile(namesFile, 'utf8'))
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean).length;
    if (count > 0) {
      return count;
    }
  } catch {
    // File missing — run fetch-tools first.
  }

  const toolsFile = process.env.MCP_TOOLS_OUT ?? '/tmp/mcp-tools.json';
  try {
    const tools = JSON.parse(await readFile(toolsFile, 'utf8')) as unknown[];
    if (Array.isArray(tools) && tools.length > 0) {
      return tools.length;
    }
  } catch {
    // ignore
  }

  return 0;
}

async function cmdFetchTools(): Promise<void> {
  const toolsOut = process.env.MCP_TOOLS_OUT ?? '/tmp/mcp-tools.json';
  const namesOut = process.env.MCP_ABILITY_NAMES_OUT ?? '/tmp/mcp-ability-names.txt';

  console.log(`Connecting to MCP: ${process.env.MCP_EVAL_SERVER_URL}`);

  const client = await connectMcpClient();
  try {
    const { tools, abilityNames } = await fetchOpenAiToolsFromMcp(client);
    await writeFile(toolsOut, JSON.stringify(tools, null, 2) + '\n', 'utf8');
    await writeFile(namesOut, abilityNames.join('\n') + '\n', 'utf8');
    console.log(`Wrote ${tools.length} tools to ${toolsOut}`);

    if (process.env.GITHUB_OUTPUT) {
      core.setOutput('tool_count', String(tools.length));
    }
  } finally {
    await client.close();
  }
}

async function cmdCoverage(): Promise<void> {
  const reportPath = process.env.COVERAGE_REPORT ?? join(REPO_ROOT, 'coverage-report.md');
  const namesFile = process.env.MCP_ABILITY_NAMES_OUT ?? '/tmp/mcp-ability-names.txt';
  const toolCount = await resolveToolCount();

  const result = await runCoverageCheck({
    repoRoot: REPO_ROOT,
    abilityNamesFile: namesFile,
    toolCount,
  });
  await writeCoverageReport(reportPath, result);
  console.log(result.reportMarkdown);

  if (result.fail) {
    process.exitCode = 1;
  }
}

async function cmdRunEvals(): Promise<void> {
  const reportPath = process.env.EVAL_REPORT ?? join(REPO_ROOT, 'eval-report.md');
  const toolsFile = process.env.MCP_TOOLS_OUT ?? '/tmp/mcp-tools.json';
  const runAll = process.env.EVAL_RUN_ALL === 'true';
  const changedSources = (process.env.EVAL_CHANGED_SOURCES ?? '')
    .split(/\s+/)
    .filter(Boolean);
  const toolCount = await resolveToolCount();

  const result = await runEvals({
    repoRoot: REPO_ROOT,
    toolsFile,
    model: process.env.MODEL ?? 'openai/gpt-4o-mini',
    gatewayUrl: process.env.CF_AI_GATEWAY_URL ?? '',
    gatewayToken: process.env.CF_AI_TOKEN ?? '',
    runAll,
    changedSources,
    toolCount,
  });

  await writeEvalReport(reportPath, result.reportMarkdown);
  console.log(result.reportMarkdown);

  if (result.fail > 0 || result.error > 0) {
    process.exitCode = 1;
  }
}

async function main(): Promise<void> {
  const command = process.argv[2];
  if (!command) {
    console.error('Usage: cli.ts <fetch-tools|coverage|run-evals>');
    process.exit(1);
  }

  if (command !== 'coverage') {
    assertMcpAuthConfigured();
  }

  switch (command) {
    case 'fetch-tools':
      await cmdFetchTools();
      break;
    case 'coverage':
      await cmdCoverage();
      break;
    case 'run-evals':
      await cmdRunEvals();
      break;
    default:
      console.error(`Unknown command: ${command}`);
      process.exit(1);
  }
}

main().catch((err) => {
  const message = err instanceof Error ? err.message : String(err);
  core.error(message);
  process.exit(1);
});
