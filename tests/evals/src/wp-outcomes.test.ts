import assert from 'node:assert/strict';
import test from 'node:test';
import { pageHasImage, pageImageReplaced, productHasTerms, WordPressOutcomes } from './wp-outcomes.js';

const serverUrl = 'http://localhost:8888/index.php?rest_route=/blu/mcp';

test('page outcome lookup finds an unpublished draft by exact title', async () => {
  const originalFetch = globalThis.fetch;
  const urls: URL[] = [];
  const headers: Array<string | null> = [];
  globalThis.fetch = async (input, init) => {
    const url = new URL(String(input));
    urls.push(url);
    headers.push(new Headers(init?.headers).get('Authorization'));
    const route = url.searchParams.get('rest_route');
    // Drafts have an empty slug, so a slug filter would find nothing.
    return new Response(JSON.stringify(route === '/wp/v2/pages'
      ? [{ id: 41, slug: '', title: { raw: 'AI Landing Page Copy' } }, { id: 42, slug: '', title: { raw: 'AI Landing Page' } }]
      : { content: { raw: route === '/wp/v2/pages/42' ? '<!-- wp:image -->' : '' } }), { status: 200 });
  };
  try {
    assert.match(await new WordPressOutcomes(serverUrl, 'Basic dGVzdA==').pageContent(), /wp:image/);
    assert.equal(urls[0].searchParams.get('status'), 'any');
    assert.equal(urls[0].searchParams.get('search'), 'AI Landing Page');
    assert.equal(urls[0].searchParams.has('slug'), false);
    assert.deepEqual(headers, ['Basic dGVzdA==', 'Basic dGVzdA==']);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('page image outcome requires an image block or tag', () => {
  assert.equal(pageHasImage('<!-- wp:paragraph --><p>Hello</p>'), false);
  assert.equal(pageHasImage('<!-- wp:image {"id":5} --><figure><img src="logo.png"></figure>'), true);
});

test('publishing outcome accepts a page already published by an earlier step', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (input) => {
    const route = new URL(String(input)).searchParams.get('rest_route');
    return new Response(JSON.stringify(route === '/wp/v2/pages' ? [{ id: 39, title: { raw: 'AI Landing Page' } }] : { status: 'publish' }), { status: 200 });
  };
  try {
    assert.equal(await new WordPressOutcomes(serverUrl, 'Basic dGVzdA==').pageStatus(), 'publish');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('image replacement requires a different image, not a text-only edit', () => {
  const before = '<!-- wp:paragraph --><p>Old</p><!-- /wp:paragraph --><!-- wp:image {"id":5,"sizeSlug":"large"} --><figure><img src="http://x.test/a.png" class="wp-image-5"/></figure><!-- /wp:image -->';
  const textOnly = before.replace('Old', 'New');
  const replaced = before.replaceAll('"id":5', '"id":9').replace('a.png', 'b.png').replace('wp-image-5', 'wp-image-9');
  const added = `${before}<!-- wp:image {"id":9} --><figure><img src="http://x.test/b.png"/></figure><!-- /wp:image -->`;
  const nested = '<!-- wp:image {"id":9,"style":{"border":{"radius":"4px"}}} --><figure><img src="http://x.test/b.png"/></figure><!-- /wp:image -->';
  assert.equal(pageImageReplaced(before, textOnly), false);
  assert.equal(pageImageReplaced(before, added), false);
  assert.equal(pageImageReplaced(before, replaced), true);
  assert.equal(pageImageReplaced(before, nested), true);
  assert.equal(pageImageReplaced(before, '<p>No image</p>'), false);
});

test('product term outcome requires both requested terms', () => {
  const product = { categories: [{ name: 'AI Tools' }], tags: [{ name: 'Automation' }] };
  assert.equal(productHasTerms(product, 'AI Tools', 'Automation'), true);
  assert.equal(productHasTerms(product, 'AI Tools', 'News'), false);
});
