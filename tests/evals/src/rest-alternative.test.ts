import assert from 'node:assert/strict';
import test from 'node:test';
import { matchesRestAlternative } from './rest-alternative.js';

const expected = [{ route: '/wp/v2/posts', method: 'POST' }];
const success = { structuredContent: { statusCode: 201, status: 'success' }, content: [] };
const failure = { structuredContent: { statusCode: 403, status: 'error' }, content: [] };

test('accepts a successful matching REST dispatch through the ability gateway', () => {
  assert.equal(matchesRestAlternative('blu-call-ability', JSON.stringify({
    ability_name: 'blu-run-api-function',
    parameters: { route: '/wp/v2/posts', method: 'POST', data: { title: 'Test' } },
  }), expected, success), true);
});

test('rejects wrong method, route, and discovery-only calls', () => {
  for (const [name, args] of [
    ['blu-call-ability', { ability_name: 'blu-run-api-function', parameters: { route: '/wp/v2/posts', method: 'GET' } }],
    ['blu-call-ability', { ability_name: 'blu-run-api-function', parameters: { route: '/wp/v2/pages', method: 'POST' } }],
    ['blu-call-ability', { ability_name: 'blu-list-api-functions', parameters: { route: '/wp/v2/posts', method: 'POST' } }],
  ] as const) {
    assert.equal(matchesRestAlternative(name, JSON.stringify(args), expected, success), false);
  }
});

test('rejects failed REST dispatch and unlisted alternatives', () => {
  const args = JSON.stringify({ ability_name: 'blu-run-api-function', parameters: { route: '/wp/v2/posts', method: 'POST' } });
  assert.equal(matchesRestAlternative('blu-call-ability', args, expected, failure), false);
  assert.equal(matchesRestAlternative('blu-call-ability', args, [], success), false);
});

test('ignores an embedded query string and trailing slash on the route', () => {
  for (const route of ['/wp/v2/posts?status=draft', '/wp/v2/posts/']) {
    const args = JSON.stringify({ ability_name: 'blu-run-api-function', parameters: { route, method: 'POST' } });
    assert.equal(matchesRestAlternative('blu-call-ability', args, expected, success), true);
  }
  const nested = JSON.stringify({ ability_name: 'blu-run-api-function', parameters: { route: '/wp/v2/posts/5', method: 'POST' } });
  assert.equal(matchesRestAlternative('blu-call-ability', nested, expected, success), false);
});
