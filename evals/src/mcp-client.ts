import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { resolveMcpAuthorization } from './auth.js';

export function requireEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
}

export function mcpServerUrl(): string {
  return (
    process.env.MCP_EVAL_SERVER_URL?.trim() ||
    'http://localhost:8888/wp-json/blu/mcp'
  );
}

export async function connectMcpClient(): Promise<Client> {
  const serverUrl = mcpServerUrl();
  const { authorization } = resolveMcpAuthorization();
  console.log(`Connecting to MCP: ${serverUrl}`);

  const transport = new StreamableHTTPClientTransport(new URL(serverUrl), {
    requestInit: {
      headers: {
        Authorization: authorization,
      },
    },
  });

  const client = new Client({
    name: 'wp-module-mcp-evals',
    version: '1.0.0',
  });

  await client.connect(transport);
  return client;
}
