/**
 * MCP eval auth: Bearer JWT (staging/CI) or Basic (local WordPress application password).
 *
 * Env (use one):
 * - MCP_EVAL_AUTH_BASIC — `username:application-password`, or pre-encoded base64, or `Basic <base64>`
 * - MCP_EVAL_AUTH_TOKEN — Hiive/Jarvis JWT (sent as `Authorization: Bearer …`)
 *
 * If both are set, Basic is used (typical for local overrides).
 */

export type McpAuthMode = 'basic' | 'bearer';

export interface McpAuthConfig {
  authorization: string;
  mode: McpAuthMode;
}

export function resolveMcpAuthorization(): McpAuthConfig {
  const basic = process.env.MCP_EVAL_AUTH_BASIC?.trim();
  const token = process.env.MCP_EVAL_AUTH_TOKEN?.trim();

  if (basic) {
    return { authorization: formatBasicAuthorization(basic), mode: 'basic' };
  }

  if (token) {
    const jwt = token.replace(/^Bearer\s+/i, '');
    return { authorization: `Bearer ${jwt}`, mode: 'bearer' };
  }

  throw new Error(
    'Set MCP_EVAL_AUTH_BASIC (user:app-password for local) or MCP_EVAL_AUTH_TOKEN (Hiive JWT for staging/CI)'
  );
}

function formatBasicAuthorization(value: string): string {
  const trimmed = value.trim();
  if (/^Basic\s+/i.test(trimmed)) {
    return trimmed;
  }

  // username:password (application password may contain spaces).
  if (trimmed.includes(':')) {
    const encoded = Buffer.from(trimmed, 'utf8').toString('base64');
    return `Basic ${encoded}`;
  }

  // Already base64-encoded credentials (Postman-style body only).
  return `Basic ${trimmed}`;
}

export function assertMcpAuthConfigured(): void {
  resolveMcpAuthorization();
}
