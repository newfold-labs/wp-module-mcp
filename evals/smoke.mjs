import assert from 'node:assert/strict';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const url = process.env.MCP_EVAL_SERVER_URL || 'http://localhost:8888/wp-json/blu/mcp';
const user = process.env.WP_EVAL_USER;
const password = process.env.WP_APP_PASSWORD;
assert.ok(user && password, 'WP_EVAL_USER and WP_APP_PASSWORD are required');

const transport = new StreamableHTTPClientTransport(new URL(url), {
  requestInit: {
    headers: {
      Authorization: `Basic ${Buffer.from(`${user}:${password}`).toString('base64')}`,
    },
  },
});
const client = new Client({ name: 'wp-module-mcp-smoke', version: '1.0.0' });

function payload(result) {
  assert.ok(!result.isError, 'MCP tool returned isError');
  const text = result.content?.find((part) => part.type === 'text')?.text;
  const value = result.structuredContent ?? (text ? JSON.parse(text) : undefined);
  assert.ok(value && typeof value === 'object', 'Expected a JSON tool response');
  assert.equal(value.statusCode, 200, JSON.stringify(value.message).slice(0, 300));
  return value.message;
}

async function call(name, args) {
  return payload(await client.callTool({ name, arguments: args }));
}

try {
  await client.connect(transport);

  const { tools } = await client.listTools();
  assert.deepEqual(
    tools.map((tool) => tool.name).sort(),
    ['blu-call-ability', 'blu-get-ability-schema', 'blu-list-abilities'],
    'MCP must expose exactly the three gateway tools'
  );

  const { prompts } = await client.listPrompts();
  const promptNames = new Set(prompts.map((prompt) => prompt.name));
  for (const name of [
    'blu-suggest-product-description',
    'blu-suggest-product-categories',
    'blu-suggest-product-tag',
    'blu-smart-product-details',
    'blu-guided-product-creation-prompt',
    'blu-suggest-product-brand',
  ]) {
    assert.ok(promptNames.has(name), `${name} is unavailable from prompts/list`);
  }
  const guided = await client.getPrompt({
    name: 'blu-guided-product-creation-prompt',
    arguments: { product_name: 'MCP Smoke Test Product' },
  });
  assert.ok(guided.messages.length > 0, 'prompts/get must return guided product messages');

  const abilities = await call('blu-list-abilities', { name_prefix: 'blu-' });
  assert.ok(Array.isArray(abilities), 'Ability catalog must be an array');
  const globalStylesListing = abilities.find((ability) => ability.name === 'blu-update-global-styles');
  assert.ok(globalStylesListing, 'Global styles ability must be discoverable');
  const globalStylesSchema = await call('blu-get-ability-schema', { ability_name: 'blu-update-global-styles' });
  assert.ok(globalStylesListing.description.length < globalStylesSchema.description.length,
    'Discovery summary must be shorter than the full schema instructions');
  for (const name of ['blu-list-api-functions', 'blu-get-function-details', 'blu-run-api-function']) {
    assert.ok(abilities.some((ability) => ability.name === name), `${name} is unavailable`);
    const schema = await call('blu-get-ability-schema', { ability_name: name });
    assert.equal(schema.name, name);
    assert.equal(schema.input_schema.type, 'object');
  }

  const endpoints = await call('blu-call-ability', {
    ability_name: 'blu-list-api-functions',
    parameters: { namespace: 'wp/v2', search: '/posts' },
  });
  assert.ok(
    Array.isArray(endpoints) && endpoints.some((item) => item.route === '/wp/v2/posts' && item.method === 'GET'),
    'GET /wp/v2/posts must be discoverable'
  );

  const details = await call('blu-call-ability', {
    ability_name: 'blu-get-function-details',
    parameters: { route: '/wp/v2/posts', method: 'GET' },
  });
  assert.ok(details.args && typeof details.args === 'object', 'Endpoint details must include args');

  const response = await call('blu-call-ability', {
    ability_name: 'blu-run-api-function',
    parameters: { route: '/wp/v2/posts', method: 'GET', data: { per_page: 1 } },
  });
  assert.ok(Array.isArray(response), 'GET /wp/v2/posts must return a list');

  console.log('MCP smoke passed: 3 gateway tools, prompts, discovery summaries, schemas, and REST dispatch');
} finally {
  await client.close();
}
