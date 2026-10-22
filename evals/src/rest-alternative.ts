import { normalizeToolName, slashAbilityToMcpName } from './tool-names.js';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';
import { parseBluPayload } from './parse-response.js';

export interface RestAlternative {
  route: string;
  method: string;
}

/** Only an explicitly listed, successfully executed REST request counts as an alternative. */
export function matchesRestAlternative(
  toolName: string,
  argsJson: string | undefined,
  alternatives: RestAlternative[],
  result: CallToolResult
): boolean {
  if (alternatives.length === 0 || !argsJson) {
    return false;
  }
  if (!normalizeToolName(toolName).endsWith('call-ability')) {
    return false;
  }
  try {
    const args = JSON.parse(argsJson) as Record<string, unknown>;
    const name = args.ability_name ?? args['ability-name'];
    if (typeof name !== 'string' || normalizeToolName(slashAbilityToMcpName(name)) !== 'blu_run-api-function') {
      return false;
    }
    const params = args.parameters;
    if (!params || typeof params !== 'object' || Array.isArray(params)) {
      return false;
    }
    const { route, method } = params as Record<string, unknown>;
    if (!alternatives.some((alternative) => route === alternative.route && method === alternative.method)) {
      return false;
    }
    const payload = parseBluPayload(result, 'REST alternative');
    return typeof payload.statusCode === 'number' && payload.statusCode >= 200 && payload.statusCode < 400;
  } catch {
    return false;
  }
}
