import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';

export interface BluAbilityPayload {
  statusCode?: number;
  status?: string;
  message?: unknown;
}

function getTextContent(result: CallToolResult): string | undefined {
  const textPart = result.content?.find((part) => part.type === 'text');
  if (textPart && 'text' in textPart && typeof textPart.text === 'string') {
    return textPart.text;
  }
  return undefined;
}

function parseJsonText(text: string, context: string): unknown {
  const trimmed = text.trim();
  if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) {
    throw new Error(
      `${context} returned plain text (not JSON). Server said: ${trimmed.slice(0, 400)}`
    );
  }
  try {
    return JSON.parse(trimmed) as unknown;
  } catch {
    throw new Error(
      `${context} returned invalid JSON. Body starts with: ${trimmed.slice(0, 400)}`
    );
  }
}

/** Unwrap WordPress BLU ability responses from tools/call. */
export function parseBluPayload(result: CallToolResult, context = 'MCP tool'): BluAbilityPayload {
  if (result.isError) {
    const text = getTextContent(result);
    throw new Error(
      `${context} failed (isError): ${text ?? JSON.stringify(result.structuredContent ?? {})}`
    );
  }

  let raw: unknown;

  if (result.structuredContent != null) {
    if (typeof result.structuredContent === 'object') {
      raw = result.structuredContent;
    } else if (typeof result.structuredContent === 'string') {
      raw = parseJsonText(result.structuredContent, context);
    }
  }

  if (raw === undefined) {
    const text = getTextContent(result);
    if (text) {
      raw = parseJsonText(text, context);
    }
  }

  if (!raw || typeof raw !== 'object') {
    throw new Error(`${context} returned no parseable payload`);
  }

  const payload = raw as BluAbilityPayload;

  if (payload.status === 'error' || (payload.statusCode && payload.statusCode >= 400)) {
    const msg =
      typeof payload.message === 'string'
        ? payload.message
        : JSON.stringify(payload.message ?? payload);
    throw new Error(`${context} returned error ${payload.statusCode ?? ''}: ${msg}`);
  }

  return payload;
}

export interface ListedAbility {
  name: string;
  namespace?: string;
  label?: string;
  description?: string;
}

export function parseAbilityList(payload: BluAbilityPayload): ListedAbility[] {
  const message = payload.message;
  if (typeof message === 'string') {
    throw new Error(`list-abilities failed: ${message}`);
  }
  if (!Array.isArray(message)) {
    throw new Error('list-abilities message is not an array');
  }
  return message.filter(
    (entry): entry is ListedAbility =>
      typeof entry === 'object' && entry !== null && typeof (entry as ListedAbility).name === 'string'
  );
}

export interface AbilitySchema {
  name: string;
  description?: string;
  input_schema?: Record<string, unknown>;
}

export function parseAbilitySchema(payload: BluAbilityPayload): AbilitySchema {
  const message = payload.message;
  if (typeof message === 'string') {
    throw new Error(`get-ability-schema failed: ${message}`);
  }
  if (!message || typeof message !== 'object' || Array.isArray(message)) {
    throw new Error('get-ability-schema message is not an object');
  }
  const schema = message as AbilitySchema;
  if (!schema.name) {
    throw new Error('get-ability-schema missing name');
  }
  return schema;
}
