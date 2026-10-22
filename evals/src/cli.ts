#!/usr/bin/env node
import { writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as core from '@actions/core';
import { assertMcpAuthConfigured } from './auth.js';
import { connectMcpClient } from './mcp-client.js';
import { listAbilityNamesForCoverage } from './mcp-tools.js';
import { runCoverageCheck, writeCoverageReport } from './coverage.js';
import { runEvals, writeEvalReport } from './run-evals.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(__dirname, '../..');

async function cmdCoverage(): Promise<void> {
  const reportPath = process.env.COVERAGE_REPORT ?? join(REPO_ROOT, 'coverage-report.md');
  const namesFile = process.env.MCP_ABILITY_NAMES_OUT ?? '/tmp/mcp-ability-names.txt';
  let toolCount = Number(process.env.MCP_TOOL_COUNT ?? 0);

  assertMcpAuthConfigured();
  const client = await connectMcpClient();
  try {
    const abilityNames = await listAbilityNamesForCoverage(client);
    await writeFile(namesFile, abilityNames.join('\n') + '\n', 'utf8');
    if (!toolCount) {
      toolCount = abilityNames.length;
    }
    console.log(`Wrote ${abilityNames.length} ability name(s) to ${namesFile}`);
  } finally {
    await client.close();
  }

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
  const runAll = process.env.EVAL_RUN_ALL === 'true';
  const changedSources = (process.env.EVAL_CHANGED_SOURCES ?? '')
    .split(/\s+/)
    .filter(Boolean);

  assertMcpAuthConfigured();

  const result = await runEvals({
    repoRoot: REPO_ROOT,
    model: process.env.MODEL ?? 'openai/gpt-5-mini',
    gatewayUrl: process.env.CF_AI_GATEWAY_URL ?? '',
    gatewayToken: process.env.CF_AI_TOKEN ?? '',
    runAll,
    changedSources,
  });

  await writeEvalReport(reportPath, result.reportMarkdown);
  console.log(result.reportMarkdown);

  if (process.env.GITHUB_OUTPUT) {
    core.setOutput('tool_count', String(result.toolCount));
  }

  if (result.fail > 0 || result.error > 0) {
    process.exitCode = 1;
  }
}

async function main(): Promise<void> {
  const command = process.argv[2];
  if (!command) {
    console.error('Usage: cli.ts <coverage|run-evals>');
    process.exit(1);
  }

  switch (command) {
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
