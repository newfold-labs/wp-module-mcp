import assert from 'node:assert/strict';
import test from 'node:test';
import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { runAgentEval } from './agent-loop.js';

test('outcome cases keep running after a selected tool until WordPress state matches', async () => {
  const originalFetch = globalThis.fetch;
  let turn = 0;
  let completed = false;
  const calls: string[] = [];
  let listCalls = 0;
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
    listTools: async () => {
      listCalls++;
      return { tools: [{ name: 'blu-call-ability', description: 'Call an ability', inputSchema: { type: 'object', properties: {} } }] };
    },
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
    assert.equal(listCalls, 1);
    await runAgentEval({ client, prompt: 'Another task', expectedTool: 'blu_add-cpt',
      model: 'test', gatewayUrl: 'http://example.invalid', gatewayToken: 'test',
      outcomeCheck: async () => true,
      tools: [{ type: 'function', function: { name: 'blu-call-ability', description: 'Call an ability', parameters: { type: 'object', properties: {} } } }],
    });
    assert.equal(listCalls, 1, 'a run-level snapshot avoids another tools/list call');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('reports expected-tool selection separately from failed execution', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response(JSON.stringify({ choices: [{ message: {
    role: 'assistant', tool_calls: [{ id: 'failed', type: 'function', function: {
      name: 'blu-call-ability', arguments: '{"ability_name":"blu-add-cpt","parameters":{}}',
    } }],
  } }] }), { status: 200 });
  const client = { callTool: async () => ({ structuredContent: { statusCode: 400, status: 'error' }, content: [] }) } as unknown as Client;
  try {
    const result = await runAgentEval({ client, prompt: 'Create a book', expectedTool: 'blu_add-cpt',
      model: 'test', gatewayUrl: 'http://example.invalid', gatewayToken: 'test', maxTurns: 1,
      tools: [{ type: 'function', function: { name: 'blu-call-ability', description: 'Call', parameters: { type: 'object', properties: {} } } }],
    });
    assert.equal(result.matched, true);
    assert.equal(result.executionFailed, true);
  } finally { globalThis.fetch = originalFetch; }
});

test('retries one AI gateway timeout before classifying an infrastructure error', async () => {
  const originalFetch = globalThis.fetch;
  let attempts = 0;
  globalThis.fetch = async () => {
    attempts++;
    if (attempts === 1) throw new DOMException('The operation was aborted due to timeout', 'TimeoutError');
    return new Response(JSON.stringify({ choices: [{ message: { role: 'assistant', content: 'Done' } }] }), { status: 200 });
  };
  try {
    await runAgentEval({ client: {} as Client, prompt: 'Check styles', expectedTool: 'blu_get-active-global-styles',
      model: 'test', gatewayUrl: 'http://example.invalid', gatewayToken: 'test',
      tools: [],
    });
    assert.equal(attempts, 2);
  } finally { globalThis.fetch = originalFetch; }
});

test('a rejection test passes only when the selected upload rejects the file', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response(JSON.stringify({ choices: [{ message: {
    role: 'assistant', tool_calls: [{ id: 'upload', type: 'function', function: {
      name: 'blu-call-ability', arguments: '{"ability_name":"blu-upload-media","parameters":{}}',
    } }],
  } }] }), { status: 200 });
  try {
    for (const [statusCode, expectedFailed] of [[400, false], [201, true]]) {
      const client = { callTool: async () => ({ structuredContent: { statusCode }, content: [] }) } as unknown as Client;
      const result = await runAgentEval({ client, prompt: 'Upload an invalid file', expectedTool: 'blu_upload-media',
        expectToolError: true, model: 'test', gatewayUrl: 'http://example.invalid', gatewayToken: 'test', maxTurns: 1,
        tools: [{ type: 'function', function: { name: 'blu-call-ability', description: 'Call', parameters: { type: 'object', properties: {} } } }],
      });
      assert.equal(result.executionFailed ?? false, expectedFailed);
    }
  } finally { globalThis.fetch = originalFetch; }
});

test('accumulates token usage and estimated cost across agent turns and gateway cache hits', async () => {
  const originalFetch = globalThis.fetch;
  let turn = 0;
  globalThis.fetch = async () => {
    turn++;
    if (turn === 1) {
      return new Response(
        JSON.stringify({
          model: 'gpt-5-mini-2025-08-07',
          usage: {
            prompt_tokens: 1500,
            completion_tokens: 100,
            total_tokens: 1600,
            prompt_tokens_details: { cached_tokens: 500 },
          },
          choices: [{
            message: {
              role: 'assistant',
              tool_calls: [{
                id: '1',
                type: 'function',
                function: { name: 'blu-list-abilities', arguments: '{"search":"posts"}' },
              }],
            },
          }],
        }),
        { status: 200 }
      );
    }
    return new Response(
      JSON.stringify({
        model: 'gpt-5-mini-2025-08-07',
        usage: { prompt_tokens: 1800, completion_tokens: 120, total_tokens: 1920 },
        choices: [{
          message: {
            role: 'assistant',
            tool_calls: [{
              id: '2',
              type: 'function',
              function: {
                name: 'blu-call-ability',
                arguments: '{"ability_name":"blu-posts-search","parameters":{}}',
              },
            }],
          },
        }],
      }),
      { status: 200, headers: { 'cf-aig-cache-status': 'HIT' } }
    );
  };
  const client = {
    callTool: async () => ({ structuredContent: { statusCode: 200, status: 'success' }, content: [] }),
  } as unknown as Client;
  try {
    const result = await runAgentEval({
      client,
      prompt: 'Search posts',
      expectedTool: 'blu_posts-search',
      model: 'openai/gpt-5-mini',
      gatewayUrl: 'http://example.invalid',
      gatewayToken: 'test',
      tools: [{ type: 'function', function: { name: 'blu-call-ability', description: 'Call', parameters: { type: 'object', properties: {} } } }],
    });
    assert.equal(result.matched, true);
    assert.equal(result.turns, 2);
    assert.equal(result.usage.promptTokens, 3300);
    assert.equal(result.usage.cachedTokens, 500);
    assert.equal(result.usage.completionTokens, 220);
    assert.equal(result.usage.totalTokens, 3520);
    assert.equal(result.usage.gatewayCacheHits, 1);
    // Turn 1 billed: 1000*0.25/1M + 500*0.025/1M + 100*2.0/1M = 0.0004625; Turn 2 HIT = $0
    assert.ok(result.usage.estimatedCostUsd !== null);
    assert.ok(Math.abs(result.usage.estimatedCostUsd - 0.0004625) < 1e-12);
  } finally {
    globalThis.fetch = originalFetch;
  }
});
