import assert from 'node:assert/strict';
import test from 'node:test';
import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { runAgentEval } from './agent-loop.js';

test('outcome cases keep running after a selected tool until WordPress state matches', async () => {
  const originalFetch = globalThis.fetch;
  let turn = 0;
  let completed = false;
  const calls: string[] = [];
  globalThis.fetch = async () => {
    turn++;
    return new Response(JSON.stringify({ choices: [{ message: {
      role: 'assistant',
      tool_calls: [{ id: String(turn), type: 'function', function: {
        name: 'blu-call-ability', arguments: JSON.stringify({ ability_name: turn === 1 ? 'blu-add-cpt' : 'blu-run-api-function' }),
      } }],
    } }] }), { status: 200 });
  };
  const client = {
    listTools: async () => ({ tools: [{ name: 'blu-call-ability', description: 'Call an ability', inputSchema: { type: 'object', properties: {} } }] }),
    callTool: async ({ name }: { name: string }) => {
      calls.push(name);
      if (calls.length === 2) completed = true;
      return { content: [{ type: 'text', text: '{"statusCode":201}' }] };
    },
  } as unknown as Client;
  try {
    const result = await runAgentEval({ client, prompt: 'Add page to menu', expectedTool: 'blu_add-cpt',
      model: 'test', gatewayUrl: 'http://example.invalid', gatewayToken: 'test',
      outcomeCheck: async () => completed,
    });
    assert.equal(result.matched, true);
    assert.equal(result.turns, 2);
    assert.equal(calls.length, 2);
  } finally {
    globalThis.fetch = originalFetch;
  }
});
