import { normalizeToolName, slashAbilityToMcpName } from './tool-names.js';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';
import { parseBluPayload } from './parse-response.js';

export interface RestAlternative {
  route: string;
  method: string;
}

function normalizeRoute(route: string): string {
  const path = route.split('?', 1)[0];
  return path.length > 1 ? path.replace(/\/+$/, '') : path;
}

function parseRunApiCall(
  toolName: string,
  argsJson: string | undefined
): { route: string; method: unknown } | undefined {
  if (!argsJson || !normalizeToolName(toolName).endsWith('call-ability')) {
    return undefined;
  }
  try {
    const args = JSON.parse(argsJson) as Record<string, unknown>;
    const name = args.ability_name ?? args['ability-name'];
    if (typeof name !== 'string' || normalizeToolName(slashAbilityToMcpName(name)) !== 'blu_run-api-function') {
      return undefined;
    }
    const params = args.parameters;
    if (!params || typeof params !== 'object' || Array.isArray(params)) {
      return undefined;
    }
    const { route, method } = params as Record<string, unknown>;
    if (typeof route !== 'string') {
      return undefined;
    }
    return { route, method };
  } catch {
    return undefined;
  }
}

/** A successful blu-run-api-function dispatch through blu-call-ability. */
export function isSuccessfulRunApiCall(
  toolName: string,
  argsJson: string | undefined,
  result: CallToolResult
): boolean {
  if (!parseRunApiCall(toolName, argsJson)) {
    return false;
  }
  try {
    const payload = parseBluPayload(result, 'run-api call');
    return typeof payload.statusCode === 'number' && payload.statusCode >= 200 && payload.statusCode < 400;
  } catch {
    return false;
  }
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
  const runApi = parseRunApiCall(toolName, argsJson);
  if (!runApi) {
    return false;
  }
  try {
    // RestApiCrud moves an embedded query string into request params; compare only the endpoint path.
    const path = normalizeRoute(runApi.route);
    if (!alternatives.some((alternative) => path === normalizeRoute(alternative.route) && runApi.method === alternative.method)) {
      return false;
    }
    const payload = parseBluPayload(result, 'REST alternative');
    return typeof payload.statusCode === 'number' && payload.statusCode >= 200 && payload.statusCode < 400;
  } catch {
    return false;
  }
}
