import { readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import * as core from '@actions/core';
import { loadPhpAbilityNames } from './php-abilities.js';
import { normalizeToolName } from './tool-names.js';

export interface TestCase {
  id: string;
  description: string;
  prompt: string;
  expected_tool: string;
  source: string;
  /** When set, consecutive cases with the same id share chat history (post id, etc.). */
  series_id?: string;
}

export interface CoverageResult {
  fail: boolean;
  missingTests: string[];
  missingOnMcp: string[];
  reportMarkdown: string;
}

export async function runCoverageCheck(options: {
  repoRoot: string;
  abilityNamesFile: string;
  toolCount: number;
}): Promise<CoverageResult> {
  const abilitiesDir = join(options.repoRoot, 'includes/Abilities');
  const testCasesPath = join(options.repoRoot, 'evals/test-cases.json');

  const phpToolsNorm = await loadPhpAbilityNames(abilitiesDir);
  const mcpNamesRaw = (await readFile(options.abilityNamesFile, 'utf8'))
    .split('\n')
    .map((l) => l.trim())
    .filter(Boolean);
  const mcpToolsNorm = [...new Set(mcpNamesRaw.map(normalizeToolName))].sort();

  const testCases = JSON.parse(await readFile(testCasesPath, 'utf8')) as TestCase[];
  const testCaseTools = [...new Set(testCases.map((t) => t.expected_tool))].sort();

  const missingTests = phpToolsNorm.filter((t) => !testCaseTools.includes(t));
  const missingOnMcp = phpToolsNorm.filter((t) => !mcpToolsNorm.includes(t));

  const lines: string[] = [];

  if (missingTests.length > 0 || missingOnMcp.length > 0) {
    lines.push('## :warning: Tool Coverage Gaps', '');
  }

  if (missingTests.length > 0) {
    lines.push(
      `**${missingTests.length} ability(s) in this repo missing test cases in \`evals/test-cases.json\`:**`,
      ''
    );
    for (const t of missingTests) {
      lines.push(`- \`${t}\``);
    }
    lines.push('');
  }

  if (missingOnMcp.length > 0) {
    lines.push(
      `**${missingOnMcp.length} ability(s) in this repo not exposed on the MCP eval server (deploy or whitelist):**`,
      ''
    );
    for (const t of missingOnMcp) {
      lines.push(`- \`${t}\``);
    }
    lines.push('');
  }

  const fail = missingTests.length > 0 || missingOnMcp.length > 0;

  if (!fail) {
    lines.push('## :white_check_mark: Tool Coverage', '');
    lines.push(
      `All repo abilities have test cases and appear on the MCP server (**${options.toolCount}** abilities).`,
      ''
    );
  }

  const reportMarkdown = lines.join('\n');

  if (process.env.GITHUB_OUTPUT) {
    core.setOutput('coverage_fail', String(fail));
    core.setOutput('missing_tests', String(missingTests.length));
    core.setOutput('missing_on_mcp', String(missingOnMcp.length));
  }

  return { fail, missingTests, missingOnMcp, reportMarkdown };
}

export async function writeCoverageReport(
  reportPath: string,
  result: CoverageResult
): Promise<void> {
  await writeFile(reportPath, result.reportMarkdown, 'utf8');
}
