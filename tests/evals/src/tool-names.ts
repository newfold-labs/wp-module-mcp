/**
 * Normalize MCP hyphen names and eval test-case names for comparison.
 * MCP: blu-get-site-info  →  test-cases: blu_get-site-info
 */
export function normalizeToolName(name: string): string {
  if (!name) {
    return '';
  }
  if (name.startsWith('blu-')) {
    return `blu_${name.slice(4)}`;
  }
  return name;
}

/** Slash ability id (blu/get-site-info) → MCP hyphen name (blu-get-site-info). */
export function slashAbilityToMcpName(slashName: string): string {
  return slashName.replace(/\//g, '-');
}
