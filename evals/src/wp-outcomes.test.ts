import assert from 'node:assert/strict';
import test from 'node:test';
import { pageHasImage, productHasTerms, WordPressOutcomes } from './wp-outcomes.js';

test('page outcome lookup includes draft pages', async () => {
  const originalFetch = globalThis.fetch;
  const urls: URL[] = [];
  globalThis.fetch = async (input) => {
    const url = new URL(String(input));
    urls.push(url);
    return new Response(JSON.stringify(url.searchParams.get('rest_route') === '/wp/v2/pages'
      ? [{ id: 42 }] : { content: { raw: '<!-- wp:image -->' } }), { status: 200 });
  };
  try {
    assert.match(await new WordPressOutcomes('http://localhost:8888/index.php?rest_route=/blu/mcp', 'user', 'pass').pageContent(), /wp:image/);
    assert.equal(urls[0].searchParams.get('status'), 'any');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('page image outcome requires an image block or tag', () => {
  assert.equal(pageHasImage('<!-- wp:paragraph --><p>Hello</p>'), false);
  assert.equal(pageHasImage('<!-- wp:image {"id":5} --><figure><img src="logo.png"></figure>'), true);
});

test('product term outcome requires both requested terms', () => {
  const product = { categories: [{ name: 'AI Tools' }], tags: [{ name: 'Automation' }] };
  assert.equal(productHasTerms(product, 'AI Tools', 'Automation'), true);
  assert.equal(productHasTerms(product, 'AI Tools', 'News'), false);
});
