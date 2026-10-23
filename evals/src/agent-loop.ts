import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';
import {
  fetchOpenAiToolsFromList,
  isGatewayMetaTool,
  matchesExpectedToolCall,
  type OpenAiFunctionTool,
} from './mcp-tools.js';
import { normalizeToolName } from './tool-names.js';
import { matchesRestAlternative, type RestAlternative } from './rest-alternative.js';

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
  restAlternatives?: RestAlternative[];
  /** Negative case: the selected ability must reject the supplied input. */
  expectToolError?: boolean;
  model: string;
  gatewayUrl: string;
  gatewayToken: string;
  maxTurns?: number;
  /** Reuse the run's tools/list snapshot; gateway discovery uses blu-list-abilities. */
  tools?: OpenAiFunctionTool[];
  /** For stateful cases, score the verified WordPress outcome instead of a selected call. */
  outcomeCheck?: () => Promise<boolean>;
  /** Prior turns from a series; the current prompt is appended as a new user message. */
  conversationMessages?: ChatMessage[];
}

export interface AgentEvalResult {
  matched: boolean;
  /** The expected ability was selected, but its response reported an error. */
  executionFailed?: boolean;
  restAlternative?: boolean;
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
    const toolDisplay = name
      ? args
        ? `${name}(${args.slice(0, 220)})`
        : name
      : '';
    if (name) {
      lastRelevant = toolDisplay;
    }
    if (matchesExpectedToolCall(name, args, expectedNorm)) {
      matched = true;
      lastRelevant = toolDisplay || name;
      break;
    }
  }

  return { matched, lastRelevant };
}

export async function runAgentEval(options: AgentEvalOptions): Promise<AgentEvalResult> {
  const expectedNorm = normalizeToolName(options.expectedTool);
  const maxTurns = options.maxTurns ?? DEFAULT_MAX_TURNS;
  const prior = options.conversationMessages ?? [];
  const messages: ChatMessage[] =
    prior.length > 0
      ? [...prior, { role: 'user', content: options.prompt }]
      : [{ role: 'user', content: options.prompt }];
  let lastRelevant = '';
  let matched = false;
  let executionFailed = false;
  let restAlternative = false;
  let restAlternativeTool = '';
  let tools: OpenAiFunctionTool[];
  try {
    tools = options.tools ?? await fetchOpenAiToolsFromList(options.client);
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    return {
      matched: false,
      actualTool: '',
      error: `Unable to load tools/list: ${msg}`,
      turns: 1,
      conversationMessages: messages,
    };
  }

  for (let turn = 0; turn < maxTurns; turn++) {
    let response: Response;
    try {
      for (let attempt = 0; ; attempt++) {
        try {
          response = await fetch(options.gatewayUrl, {
            method: 'POST',
            headers: {
              'cf-aig-authorization': `Bearer ${options.gatewayToken}`,
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ model: options.model, messages, tools, tool_choice: 'auto' }),
            signal: AbortSignal.timeout(60_000),
          });
          break;
        } catch (err) {
          if (attempt > 0 || !(err instanceof Error) || err.name !== 'TimeoutError') throw err;
          await new Promise((resolve) => setTimeout(resolve, 250));
        }
      }
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
      if (assistantMessage) {
        messages.push({ role: 'assistant', content: assistantMessage.content ?? null });
      }
      return {
        matched: options.outcomeCheck ? await options.outcomeCheck() : matched,
        restAlternative: options.outcomeCheck ? false : restAlternative,
        actualTool: !matched && restAlternative ? restAlternativeTool : lastRelevant || '(none)',
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
      if (matchesExpectedToolCall(toolName, call.function?.arguments, expectedNorm)) {
        let statusCode: number | undefined;
        let failed = result.isError === true;
        try {
          const payload = JSON.parse(formatToolResultForModel(result)) as { statusCode?: number; status?: string };
          statusCode = payload.statusCode;
          if (payload.status === 'error') failed = true;
        } catch { /* The response may be plain text. */ }
        if (statusCode !== undefined && statusCode >= 400) failed = true;
        executionFailed = options.expectToolError
          ? !(statusCode !== undefined && statusCode >= 400)
          : failed;
      }
      if (matchesRestAlternative(toolName, call.function?.arguments, options.restAlternatives ?? [], result)) {
        restAlternative = true;
        restAlternativeTool = `${toolName}(${(call.function?.arguments ?? '').slice(0, 220)})`;
      }
    }

    if (options.outcomeCheck && await options.outcomeCheck()) {
      return {
        matched: true,
        actualTool: lastRelevant || toolCalls[toolCalls.length - 1]?.function.name || '(none)',
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

    if (matched && !options.outcomeCheck) {
      return {
        matched: true,
        executionFailed,
        actualTool: lastRelevant,
        turns: turn + 1,
        conversationMessages: messages,
      };
    }

  }

  return {
    matched: options.outcomeCheck ? await options.outcomeCheck() : false,
    restAlternative: options.outcomeCheck ? false : restAlternative,
    actualTool: restAlternative ? restAlternativeTool : lastRelevant || '(none)',
    error: restAlternative ? undefined : `Exceeded max turns (${maxTurns}) without expected tool`,
    turns: maxTurns,
    conversationMessages: messages,
  };
}
