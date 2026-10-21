import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import type { CallToolResult, Tool } from '@modelcontextprotocol/sdk/types.js';
import { parseAbilityList, parseBluPayload } from './parse-response.js';
import { sanitizeParametersForOpenAi } from './sanitize-openai-schema.js';
import { normalizeToolName, slashAbilityToMcpName } from './tool-names.js';

type ToolLike = Tool & { input_schema?: unknown };

export interface OpenAiFunctionTool {
  type: 'function';
  function: {
    name: string;
    description: string;
    parameters: Record<string, unknown>;
  };
}

const GATEWAY_META_FRAGMENTS = ['list-abilities', 'list_abilities', 'get-ability-schema', 'get_ability_schema'];

function getToolInputSchema(tool: ToolLike): Record<string, unknown> {
  const raw = tool.inputSchema ?? tool.input_schema;
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw as Record<string, unknown>;
  }
  return { type: 'object', properties: {} };
}

/** Tools/list → OpenAI function tools (no gateway expansion). */
export function mcpToolsToOpenAi(tools: Tool[]): OpenAiFunctionTool[] {
  return tools.map((tool) => {
    const parameters = sanitizeParametersForOpenAi(getToolInputSchema(tool as ToolLike), tool.name);
    return {
      type: 'function',
      function: {
        name: tool.name,
        description: tool.description ?? tool.name,
        parameters,
      },
    };
  });
}

export async function fetchOpenAiToolsFromList(client: Client): Promise<OpenAiFunctionTool[]> {
  const { tools } = await client.listTools();
  console.log(`MCP tools/list returned ${tools.length} tool(s)`);
  return mcpToolsToOpenAi(tools);
}

function nameMatches(toolName: string, fragments: string[]): boolean {
  const n = toolName.toLowerCase();
  return fragments.some((f) => n.includes(f));
}

/** Gateway discovery/list tools — ignored when matching eval expectations. */
export function isGatewayMetaTool(toolName: string): boolean {
  return nameMatches(toolName, GATEWAY_META_FRAGMENTS);
}

function isCallAbilityTool(toolName: string): boolean {
  return nameMatches(toolName, ['call-ability', 'call_ability']);
}

export function matchesExpectedToolCall(
  toolName: string,
  argsJson: string | undefined,
  expectedNorm: string
): boolean {
  if (isGatewayMetaTool(toolName)) {
    return false;
  }

  if (normalizeToolName(toolName) === expectedNorm) {
    return true;
  }

  if (!isCallAbilityTool(toolName) || !argsJson) {
    return false;
  }

  try {
    const args = JSON.parse(argsJson) as Record<string, unknown>;
    const abilityName = args.ability_name ?? args['ability-name'];
    if (typeof abilityName === 'string') {
      return normalizeToolName(slashAbilityToMcpName(abilityName)) === expectedNorm;
    }
  } catch {
    // ignore invalid JSON
  }

  return false;
}

/** Ability hyphen names for coverage when the server exposes gateway tools only. */
export async function listAbilityNamesForCoverage(client: Client): Promise<string[]> {
  const { tools } = await client.listTools();
  const gatewayOnly =
    tools.length <= 5 && tools.some((t) => isGatewayMetaTool(t.name) || isCallAbilityTool(t.name));

  if (!gatewayOnly) {
    return tools.map((t) => t.name);
  }

  const listTool = tools.find((t) => nameMatches(t.name, ['list-abilities', 'list_abilities']))?.name;
  if (!listTool) {
    return tools.map((t) => t.name);
  }

  const listResult = (await client.callTool({
    name: listTool,
    arguments: {},
  })) as CallToolResult;
  const payload = parseBluPayload(listResult, `tools/call ${listTool}`);
  let abilities = parseAbilityList(payload);
  const namespace = process.env.MCP_EVAL_NAMESPACE ?? 'blu';
  if (namespace) {
    abilities = abilities.filter(
      (a) => a.namespace === namespace || a.name.startsWith(`${namespace}-`)
    );
  }
  return abilities.map((a) => a.name);
}
