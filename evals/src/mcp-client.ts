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

export async function connectMcpClient(): Promise<Client> {
  const serverUrl = requireEnv('MCP_EVAL_SERVER_URL');
  const { authorization, mode } = resolveMcpAuthorization();
  console.log(`MCP auth mode: ${mode}`);

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
