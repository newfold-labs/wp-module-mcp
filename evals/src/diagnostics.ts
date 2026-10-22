interface Message {
  role: string;
  content?: string | null;
  tool_call_id?: string;
  tool_calls?: Array<{ id: string; function: { name: string; arguments: string } }>;
}

/** Keep only fields useful for debugging; tool arguments can contain credentials. */
export function safeTrace(messages: Message[]): Array<Record<string, unknown>> {
  return messages.filter((message) => message.role !== 'user').map((message) => {
    if (message.role === 'assistant') {
      return {
        role: 'assistant',
        calls: message.tool_calls?.map((call) => {
          let args: Record<string, unknown> = {};
          try { args = JSON.parse(call.function.arguments) as Record<string, unknown>; } catch { /* omit malformed arguments */ }
          const params = args.parameters && typeof args.parameters === 'object' ? args.parameters as Record<string, unknown> : {};
          return {
            name: call.function.name,
            ability: typeof args.ability_name === 'string' ? args.ability_name : undefined,
            route: typeof params.route === 'string' ? params.route : undefined,
            method: typeof params.method === 'string' ? params.method : undefined,
          };
        }),
        reply: typeof message.content === 'string' ? message.content.slice(0, 500).replace(/(password\s*[:=]\s*)\S+/gi, '$1[redacted]') : undefined,
      };
    }
    let statusCode: number | undefined;
    const toolError = typeof message.content === 'string' && message.content.startsWith('MCP error:');
    if (typeof message.content === 'string') {
      try {
        const payload = JSON.parse(message.content) as Record<string, unknown>;
        if (typeof payload.statusCode === 'number') statusCode = payload.statusCode;
      } catch { /* do not include raw tool output */ }
    }
    return { role: 'tool', statusCode, toolError };
  });
}

export function summarizeFailure(messages: Message[]): string {
  const trace = safeTrace(messages);
  if (trace.some((entry) => entry.role === 'tool' && (entry.toolError || typeof entry.statusCode === 'number' && entry.statusCode >= 400))) {
    return 'Tool error';
  }
  const calls = trace.flatMap((entry) => Array.isArray(entry.calls) ? entry.calls as Array<{ name: string }> : []);
  if (calls.length === 0) {
    const reply = [...messages].reverse().find((message) => message.role === 'assistant')?.content;
    return typeof reply === 'string' && /\?|\b(?:need|provide|which|what)\b/i.test(reply) ? 'Asked for input' : 'No tool call';
  }
  if (calls.every((call) => ['blu-list-abilities', 'blu-get-ability-schema'].includes(call.name))) return 'Discovery only';
  return 'Different ability';
}
