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

  const abilities = await call('blu-list-abilities', { name_prefix: 'blu-' });
  assert.ok(Array.isArray(abilities), 'Ability catalog must be an array');
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

  console.log('MCP gateway smoke passed: 3 tools, REST discovery, schema, and GET dispatch');
} finally {
  await client.close();
}
