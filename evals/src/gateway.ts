import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import type { CallToolResult, Tool } from '@modelcontextprotocol/sdk/types.js';
import {
  parseAbilityList,
  parseAbilitySchema,
  parseBluPayload,
} from './parse-response.js';
import { sanitizeParametersForOpenAi } from './sanitize-openai-schema.js';

export interface GatewayTools {
  list: string;
  schema: string;
  call?: string;
}

type ToolLike = Tool & { input_schema?: unknown };

/** WordPress / mcp-adapter may expose input_schema (snake_case) or inputSchema. */
function getToolInputSchema(tool: ToolLike): unknown {
  return tool.inputSchema ?? tool.input_schema;
}

function hasProperty(schema: unknown, key: string): boolean {
  if (!schema || typeof schema !== 'object') {
    return false;
  }
  const props = (schema as { properties?: Record<string, unknown> }).properties;
  return props != null && key in props;
}

function hasRequired(schema: unknown, key: string): boolean {
  if (!schema || typeof schema !== 'object') {
    return false;
  }
  const required = (schema as { required?: string[] }).required;
  return Array.isArray(required) && required.includes(key);
}

function nameMatches(toolName: string, fragments: string[]): boolean {
  const n = toolName.toLowerCase();
  return fragments.some((f) => n.includes(f));
}

/** Find list / schema / call gateway tools by inputSchema shape, then by tool name. */
export function discoverGatewayTools(tools: Tool[]): GatewayTools {
  const bySchema = discoverGatewayToolsBySchema(tools);
  if (bySchema) {
    return bySchema;
  }

  const byName = discoverGatewayToolsByName(tools);
  if (byName) {
    console.log('Gateway tools identified by name (schemas were empty or non-standard)');
    return byName;
  }

  const summary = tools.map((t) => {
    const schema = getToolInputSchema(t as ToolLike);
    const props =
      schema && typeof schema === 'object'
        ? Object.keys((schema as { properties?: object }).properties ?? {})
        : [];
    return `${t.name} properties=[${props.join(', ')}]`;
  });
  throw new Error(
    `Could not identify gateway tools from tools/list (${tools.length} tools returned).\n${summary.join('\n')}`
  );
}

function discoverGatewayToolsBySchema(tools: Tool[]): GatewayTools | null {
  const list = tools.find((t) => {
    const schema = getToolInputSchema(t as ToolLike);
    return (
      hasProperty(schema, 'namespace') &&
      !hasProperty(schema, 'ability_name') &&
      !hasProperty(schema, 'parameters')
    );
  })?.name;

  const schema = tools.find((t) => {
    const s = getToolInputSchema(t as ToolLike);
    return (
      hasProperty(s, 'ability_name') &&
      !hasProperty(s, 'parameters') &&
      (hasRequired(s, 'ability_name') || !hasProperty(s, 'namespace'))
    );
  })?.name;

  const call = tools.find((t) => {
    const s = getToolInputSchema(t as ToolLike);
    return hasProperty(s, 'parameters') && hasProperty(s, 'ability_name');
  })?.name;

  if (!list || !schema) {
    return null;
  }

  return { list, schema, call };
}

function discoverGatewayToolsByName(tools: Tool[]): GatewayTools | null {
  const list = tools.find((t) =>
    nameMatches(t.name, ['list-abilities', 'list_abilities'])
  )?.name;

  const schema = tools.find((t) =>
    nameMatches(t.name, ['get-ability-schema', 'get_ability_schema'])
  )?.name;

  const call = tools.find((t) =>
    nameMatches(t.name, ['call-ability', 'call_ability'])
  )?.name;

  if (!list || !schema) {
    return null;
  }

  return { list, schema, call };
}

export interface OpenAiFunctionTool {
  type: 'function';
  function: {
    name: string;
    description: string;
    parameters: Record<string, unknown>;
  };
}

export interface FetchToolsResult {
  tools: OpenAiFunctionTool[];
  abilityNames: string[];
  gateway: GatewayTools;
}

const SCHEMA_DELAY_MS = 150;

export async function fetchOpenAiToolsFromMcp(
  client: Client,
  namespace = process.env.MCP_EVAL_NAMESPACE ?? 'blu'
): Promise<FetchToolsResult> {
  const { tools: mcpTools } = await client.listTools();
  const gateway = discoverGatewayTools(mcpTools);

  console.log(`Gateway list tool: ${gateway.list}`);
  console.log(`Gateway schema tool: ${gateway.schema}`);
  if (gateway.call) {
    console.log(`Gateway call tool: ${gateway.call}`);
  }

  // Always call with {} — matches Postman and older hosts whose list-abilities schema
  // has no `namespace` property (passing namespace causes validation errors locally).
  const listResult = (await client.callTool({
    name: gateway.list,
    arguments: {},
  })) as CallToolResult;
  const listPayload = parseBluPayload(listResult, `tools/call ${gateway.list}`);
  let abilities = parseAbilityList(listPayload);

  if (namespace) {
    const before = abilities.length;
    abilities = abilities.filter(
      (a) => a.namespace === namespace || a.name.startsWith(`${namespace}-`)
    );
    console.log(
      `Filtered list-abilities by namespace "${namespace}": ${abilities.length} of ${before}`
    );
  }

  console.log(`Discovered ${abilities.length} abilities (namespace: ${namespace || 'all'})`);

  const openAiTools: OpenAiFunctionTool[] = [];
  const abilityNames: string[] = [];

  for (const ability of abilities) {
    await sleep(SCHEMA_DELAY_MS);
    const schemaResult = (await client.callTool({
      name: gateway.schema,
      arguments: { ability_name: ability.name },
    })) as CallToolResult;
    const schemaPayload = parseBluPayload(
      schemaResult,
      `tools/call ${gateway.schema} (${ability.name})`
    );
    const schema = parseAbilitySchema(schemaPayload);

    const description =
      schema.description ?? ability.description ?? ability.label ?? ability.name;
    const rawParameters = (schema.input_schema ?? { type: 'object', properties: {} }) as Record<
      string,
      unknown
    >;
    const parameters = sanitizeParametersForOpenAi(rawParameters, schema.name);

    openAiTools.push({
      type: 'function',
      function: {
        name: schema.name,
        description,
        parameters,
      },
    });
    abilityNames.push(schema.name);
  }

  return { tools: openAiTools, abilityNames, gateway };
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
