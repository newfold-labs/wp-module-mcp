import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';

export interface ToolFailureClassification {
  /** The ability logically failed (MCP isError or legacy status envelope). */
  abilityFailed: boolean;
  /** MCP tools/call set isError: true per spec. */
  mcpReportsError: boolean;
}

function readPayload(result: CallToolResult): { statusCode?: number; status?: string } {
  if (result.structuredContent != null && typeof result.structuredContent === 'object') {
    return result.structuredContent as { statusCode?: number; status?: string };
  }
  const textPart = result.content?.find((part) => part.type === 'text');
  if (textPart && 'text' in textPart && typeof textPart.text === 'string') {
    try {
      return JSON.parse(textPart.text) as { statusCode?: number; status?: string };
    } catch {
      return {};
    }
  }
  return {};
}

/** Classify MCP tool failure signaling for eval scoring. */
export function classifyToolFailure(result: CallToolResult): ToolFailureClassification {
  const mcpReportsError = result.isError === true;
  let abilityFailed = mcpReportsError;
  const payload = readPayload(result);
  if (payload.status === 'error') {
    abilityFailed = true;
  }
  if (typeof payload.statusCode === 'number' && payload.statusCode >= 400) {
    abilityFailed = true;
  }
  return { abilityFailed, mcpReportsError };
}

/**
 * Whether the eval should flag executionFailed for the expected tool call.
 * Negative cases must reject the input and report the failure with MCP isError: true.
 */
export function shouldFlagExecutionFailed(
  result: CallToolResult,
  expectToolError: boolean | undefined
): boolean {
  const { abilityFailed, mcpReportsError } = classifyToolFailure(result);
  if (expectToolError) {
    return !abilityFailed || !mcpReportsError;
  }
  return abilityFailed;
}