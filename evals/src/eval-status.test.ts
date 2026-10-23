import assert from 'node:assert/strict';
import test from 'node:test';
import { classifyEvalResult } from './eval-status.js';

test('REST alternatives are warnings, distinct from exact passes and errors', () => {
  assert.equal(classifyEvalResult({ matched: false, restAlternative: true }), 'warning');
  assert.equal(classifyEvalResult({ matched: true, restAlternative: true }), 'pass');
  assert.equal(classifyEvalResult({ matched: false, restAlternative: true, error: 'network failure' }), 'error');
  assert.equal(classifyEvalResult({ matched: false }), 'fail');
  assert.equal(classifyEvalResult({ matched: true, executionFailed: true }), 'warning');
});
