/**
 * MCP eval auth: WordPress application password (HTTP Basic).
 *
 * Env:
 * - MCP_EVAL_AUTH_BASIC — `username:application-password`, or pre-encoded base64, or `Basic <base64>`
 * - WP_EVAL_USER + WP_APP_PASSWORD — set separately (e.g. from wp-env + WP-CLI in CI)
 */

export interface McpAuthConfig {
  authorization: string;
}

export function resolveMcpAuthorization(): McpAuthConfig {
  const basic = process.env.MCP_EVAL_AUTH_BASIC?.trim();
  if (basic) {
    return { authorization: formatBasicAuthorization(basic) };
  }

  const user = process.env.WP_EVAL_USER?.trim();
  const password = process.env.WP_APP_PASSWORD?.trim();
  if (user && password) {
    const encoded = Buffer.from(`${user}:${password}`, 'utf8').toString('base64');
    return { authorization: `Basic ${encoded}` };
  }

  throw new Error(
    'Set MCP_EVAL_AUTH_BASIC (user:app-password) or WP_EVAL_USER and WP_APP_PASSWORD'
  );
}

function formatBasicAuthorization(value: string): string {
  const trimmed = value.trim();
  if (/^Basic\s+/i.test(trimmed)) {
    return trimmed;
  }

  if (trimmed.includes(':')) {
    const encoded = Buffer.from(trimmed, 'utf8').toString('base64');
    return `Basic ${encoded}`;
  }

  return `Basic ${trimmed}`;
}

export function assertMcpAuthConfigured(): void {
  resolveMcpAuthorization();
}
