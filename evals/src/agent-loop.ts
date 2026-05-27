import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';
import {
  fetchOpenAiToolsFromList,
  isGatewayMetaTool,
  matchesExpectedToolCall,
  type OpenAiFunctionTool,
} from './mcp-tools.js';
import { normalizeToolName } from './tool-names.js';

const DEFAULT_MAX_TURNS = 20;

interface ChatMessage {
  role: string;
  content?: string | null;
  tool_calls?: Array<{
    id: string;
    type: 'function';
    function: { name: string; arguments: string };
  }>;
  tool_call_id?: string;
}

interface ChatCompletionResponse {
  choices?: Array<{
    message?: ChatMessage;
    finish_reason?: string;
  }>;
  error?: { message?: string };
}

export interface AgentEvalOptions {
  client: Client;
  prompt: string;
  expectedTool: string;
  model: string;
  gatewayUrl: string;
  gatewayToken: string;
  maxTurns?: number;
  /** Prior turns from a series; the current prompt is appended as a new user message. */
  conversationMessages?: ChatMessage[];
}

export interface AgentEvalResult {
  matched: boolean;
  actualTool: string;
  error?: string;
  turns: number;
  /** Full chat history after this step (pass to the next step in a series). */
  conversationMessages: ChatMessage[];
}

function formatToolResultForModel(result: CallToolResult): string {
  if (result.structuredContent != null) {
    return typeof result.structuredContent === 'string'
      ? result.structuredContent
      : JSON.stringify(result.structuredContent);
  }
  const textPart = result.content?.find((part) => part.type === 'text');
  if (textPart && 'text' in textPart && typeof textPart.text === 'string') {
    return textPart.text;
  }
  return JSON.stringify(result);
}

function collectMatchingCalls(
  toolCalls: NonNullable<ChatMessage['tool_calls']>,
  expectedNorm: string
): { matched: boolean; lastRelevant: string } {
  let lastRelevant = '';
  let matched = false;

  for (const call of toolCalls) {
    const name = call.function?.name ?? '';
    const args = call.function?.arguments;
    if (isGatewayMetaTool(name)) {
      continue;
    }
    if (name) {
      lastRelevant = args ? `${name}(${args.slice(0, 80)})` : name;
    }
    if (matchesExpectedToolCall(name, args, expectedNorm)) {
      matched = true;
      lastRelevant = name;
      break;
    }
  }

  return { matched, lastRelevant };
}

export async function runAgentEval(options: AgentEvalOptions): Promise<AgentEvalResult> {
  const expectedNorm = normalizeToolName(options.expectedTool);
  const maxTurns = options.maxTurns ?? DEFAULT_MAX_TURNS;
  let tools: OpenAiFunctionTool[] = await fetchOpenAiToolsFromList(options.client);

  const prior = options.conversationMessages ?? [];
  const messages: ChatMessage[] =
    prior.length > 0
      ? [...prior, { role: 'user', content: options.prompt }]
      : [{ role: 'user', content: options.prompt }];
  let lastRelevant = '';
  let matched = false;

  for (let turn = 0; turn < maxTurns; turn++) {
    let response: Response;
    try {
      response = await fetch(options.gatewayUrl, {
        method: 'POST',
        headers: {
          'cf-aig-authorization': `Bearer ${options.gatewayToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: options.model,
          messages,
          tools,
          tool_choice: 'auto',
        }),
        signal: AbortSignal.timeout(60_000),
      });
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      return {
        matched: false,
        actualTool: '',
        error: `Request failed: ${msg}`,
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    const responseText = await response.text();
    if (response.status < 200 || response.status >= 300) {
      return {
        matched: false,
        actualTool: '',
        error: `HTTP ${response.status}: ${responseText.slice(0, 200)}`,
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    let parsed: ChatCompletionResponse;
    try {
      parsed = JSON.parse(responseText) as ChatCompletionResponse;
    } catch {
      return {
        matched: false,
        actualTool: '',
        error: 'Invalid JSON from AI gateway',
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    if (parsed.error?.message) {
      return {
        matched: false,
        actualTool: '',
        error: parsed.error.message,
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    const assistantMessage = parsed.choices?.[0]?.message;
    const toolCalls = assistantMessage?.tool_calls;

    if (!toolCalls?.length) {
      return {
        matched,
        actualTool: lastRelevant || '(none)',
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    const { matched: turnMatched, lastRelevant: turnRelevant } = collectMatchingCalls(
      toolCalls,
      expectedNorm
    );
    if (turnRelevant) {
      lastRelevant = turnRelevant;
    }
    if (turnMatched) {
      matched = true;
    }

    messages.push({
      role: 'assistant',
      content: assistantMessage?.content ?? null,
      tool_calls: toolCalls,
    });

    for (const call of toolCalls) {
      const toolName = call.function?.name ?? '';
      let args: Record<string, unknown> = {};
      try {
        args = JSON.parse(call.function?.arguments ?? '{}') as Record<string, unknown>;
      } catch {
        args = {};
      }

      let result: CallToolResult;
      try {
        result = (await options.client.callTool({
          name: toolName,
          arguments: args,
        })) as CallToolResult;
      } catch (err) {
        const msg = err instanceof Error ? err.message : String(err);
        result = {
          content: [{ type: 'text', text: `MCP error: ${msg}` }],
          isError: true,
        } as CallToolResult;
      }

      messages.push({
        role: 'tool',
        tool_call_id: call.id,
        content: formatToolResultForModel(result),
      });
    }

    if (matched) {
      return {
        matched: true,
        actualTool: lastRelevant,
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    // Refresh tools/list in case the model discovered new schemas via gateway (same set, cheap).
    tools = await fetchOpenAiToolsFromList(options.client);
  }

  return {
    matched: false,
    actualTool: lastRelevant || '(none)',
    error: `Exceeded max turns (${maxTurns}) without expected tool`,
    turns: maxTurns,
    conversationMessages: messages,
  };
}
