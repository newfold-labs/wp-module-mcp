import assert from 'node:assert/strict';
import test from 'node:test';
import { blockingPrerequisite } from './eval-dependencies.js';

test('a follow-on step is blocked when its required creation failed', () => {
  assert.equal(blockingPrerequisite(['create-book'], new Map([['create-book', 'warning']])), 'create-book');
  assert.equal(blockingPrerequisite(['create-book'], new Map([['create-book', 'pass']])), undefined);
});
