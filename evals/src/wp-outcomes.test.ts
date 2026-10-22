import assert from 'node:assert/strict';
import test from 'node:test';
import { pageHasImage, productHasTerms } from './wp-outcomes.js';

test('page image outcome requires an image block or tag', () => {
  assert.equal(pageHasImage('<!-- wp:paragraph --><p>Hello</p>'), false);
  assert.equal(pageHasImage('<!-- wp:image {"id":5} --><figure><img src="logo.png"></figure>'), true);
});

test('product term outcome requires both requested terms', () => {
  const product = { categories: [{ name: 'AI Tools' }], tags: [{ name: 'Automation' }] };
  assert.equal(productHasTerms(product, 'AI Tools', 'Automation'), true);
  assert.equal(productHasTerms(product, 'AI Tools', 'News'), false);
});
