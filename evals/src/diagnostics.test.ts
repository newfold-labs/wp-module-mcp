import assert from 'node:assert/strict';
import test from 'node:test';
import { summarizeFailure, safeTrace } from './diagnostics.js';

test('summarizes a discovery-only failure without exposing arguments', () => {
  const messages = [
    { role: 'assistant', tool_calls: [{ id: '1', type: 'function' as const, function: { name: 'blu-list-abilities', arguments: '{"search":"password Secret123"}' } }] },
    { role: 'tool', tool_call_id: '1', content: '{"statusCode":200}' },
    { role: 'assistant', content: 'I need more information.' },
  ];
  assert.equal(summarizeFailure(messages), 'Discovery only');
  const trace = JSON.stringify(safeTrace(messages));
  assert.ok(!trace.includes('Secret123'));
  assert.ok(trace.includes('blu-list-abilities'));
  assert.ok(trace.includes('I need more information'));
});

test('summarizes tool errors and different ability choices', () => {
  const call = { role: 'assistant', tool_calls: [{ id: '1', type: 'function' as const, function: { name: 'blu-call-ability', arguments: '{"ability_name":"blu-add-post"}' } }] };
  assert.equal(summarizeFailure([call, { role: 'tool', tool_call_id: '1', content: '{"statusCode":403}' }]), 'Tool error');
  assert.equal(summarizeFailure([call, { role: 'tool', tool_call_id: '1', content: 'MCP error: permission denied' }]), 'Tool error');
  assert.equal(summarizeFailure([call, { role: 'tool', tool_call_id: '1', content: '{"statusCode":200}' }]), 'Different ability');
});

test('distinguishes a clarification request from no action', () => {
  assert.equal(summarizeFailure([{ role: 'assistant', content: 'Which image file should I upload?' }]), 'Asked for input');
  assert.equal(summarizeFailure([{ role: 'assistant', content: 'Done.' }]), 'No tool call');
});

test('records a stable private discovery key to distinguish repeated searches', () => {
  const call = (search: string) => [{ role: 'assistant', tool_calls: [{ id: '1', function: { name: 'blu-list-abilities', arguments: JSON.stringify({ search }) } }] }];
  const a = JSON.stringify(safeTrace(call('secret portfolio')));
  const b = JSON.stringify(safeTrace(call('secret portfolio')));
  const c = JSON.stringify(safeTrace(call('other query')));
  assert.equal(a, b);
  assert.notEqual(a, c);
  assert.ok(!a.includes('secret portfolio'));
  assert.match(a, /discoveryKey/);
});

test('identifies a repeated discovery query without printing its terms', () => {
  const messages = Array.from({ length: 3 }, (_, index) => ({
    role: 'assistant', tool_calls: [{ id: String(index), function: { name: 'blu-list-abilities', arguments: '{"search":"private query"}' } }],
  }));
  assert.equal(summarizeFailure(messages), 'Repeated discovery query');
});
