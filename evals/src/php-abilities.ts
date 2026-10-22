import { readdir, readFile } from 'node:fs/promises';
import { basename, join } from 'node:path';
import { slashAbilityToMcpName, normalizeToolName } from './tool-names.js';

const REGISTER_ABILITY_PATTERN =
  /blu_register_ability\s*\(\s*'blu\/[a-z0-9_/-]+'/g;

/** Registered abilities from includes/Abilities/*.php (excludes AbilityGateway). */
export async function loadPhpAbilityNames(abilitiesDir: string): Promise<string[]> {
  const files = await readdir(abilitiesDir);
  const slashes = new Set<string>();

  for (const file of files) {
    if (!file.endsWith('.php') || file === 'AbilityGateway.php') {
      continue;
    }
    const content = await readFile(join(abilitiesDir, file), 'utf8');
    const matches = content.match(REGISTER_ABILITY_PATTERN) ?? [];
    for (const match of matches) {
      const slash = match.replace(/blu_register_ability\s*\(\s*'/, '').replace(/'$/, '');
      slashes.add(slash);
    }
  }

  return [...slashes]
    .map(slashAbilityToMcpName)
    .map(normalizeToolName)
    .sort();
}

export function abilitySourceFile(abilitiesDir: string, source: string): string {
  return join(abilitiesDir, basename(source));
}
