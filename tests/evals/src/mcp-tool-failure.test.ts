import assert from 'node:assert/strict';
import test from 'node:test';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';
import { classifyToolFailure, shouldFlagExecutionFailed } from './mcp-tool-failure.js';

test('classifyToolFailure requires MCP isError for spec-compliant failure reporting', () => {
  const legacy: CallToolResult = { structuredContent: { statusCode: 400, status: 'error' }, content: [] };
  const compliant: CallToolResult = {
    content: [{ type: 'text', text: 'Unsupported file type.' }],
    isError: true,
  };

  assert.deepEqual(classifyToolFailure(legacy), { abilityFailed: true, mcpReportsError: false });
  assert.deepEqual(classifyToolFailure(compliant), { abilityFailed: true, mcpReportsError: true });
  assert.deepEqual(classifyToolFailure({ content: [], isError: false }), { abilityFailed: false, mcpReportsError: false });
});

test('shouldFlagExecutionFailed for negative cases rejects legacy envelopes without isError', () => {
  const legacy: CallToolResult = { structuredContent: { statusCode: 400 }, content: [] };
  const compliant: CallToolResult = { content: [{ type: 'text', text: 'Denied.' }], isError: true };
  const success: CallToolResult = { structuredContent: { statusCode: 201 }, content: [] };

  assert.equal(shouldFlagExecutionFailed(legacy, true), true);
  assert.equal(shouldFlagExecutionFailed(compliant, true), false);
  assert.equal(shouldFlagExecutionFailed(success, true), true);
});

test('shouldFlagExecutionFailed for positive cases flags any ability failure', () => {
  const legacy: CallToolResult = { structuredContent: { statusCode: 403, status: 'error' }, content: [] };
  assert.equal(shouldFlagExecutionFailed(legacy, false), true);
  assert.equal(shouldFlagExecutionFailed({ content: [], isError: false }, false), false);
});