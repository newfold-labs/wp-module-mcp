import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtemp, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { loadPhpAbilityNames } from './php-abilities.js';

test('tool coverage excludes MCP prompts', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'mcp-abilities-'));
  await writeFile(join(dir, 'Posts.php'), "blu_register_ability( 'blu/add-post', array() );");
  await writeFile(join(dir, 'Prompts.php'), "blu_register_ability( 'blu/guided-product-creation-prompt', array( 'meta' => array( 'mcp' => array( 'type' => 'prompt' ) ) ) );");
  assert.deepEqual(await loadPhpAbilityNames(dir), ['blu_add-post']);
});
