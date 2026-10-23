import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveEvalPrompt } from './eval-prompt.js';

test('supplies file bytes for upload cases and an actual seeded product ID', () => {
  const upload = resolveEvalPrompt('uploading-media-file-28', "Upload a media file named 'test.jpg'.", {});
  assert.match(upload, /data:image\/png;base64,/);
  assert.match(upload, /media-flow.png/);
  const product = resolveEvalPrompt('wc-product-get-1', 'Show product ID 42', { productId: 123 });
  assert.equal(product, 'Show product ID 123');
});

test('does not pretend an unseeded product ID exists', () => {
  assert.throws(() => resolveEvalPrompt('wc-product-get-1', 'Show product ID 42', {}), /product fixture/i);
});

test('replaces a standalone placeholder ID with its seeded object ID', () => {
  assert.equal(resolveEvalPrompt('post-get-1', 'Show me post with ID 42', { ids: { post_get: 317 } }), 'Show me post with ID 317');
  assert.throws(() => resolveEvalPrompt('post-get-1', 'Show me post with ID 42', {}), /fixture/i);
});

test('uses seeded category and independent homepage fixtures', () => {
  assert.equal(resolveEvalPrompt('wc-category-delete-1', 'Delete category ID 20', { ids: { wc_category_delete: 87 } }), 'Delete category ID 87');
  assert.equal(resolveEvalPrompt('setting-homepage-68', "Set homepage to 'AI Eval Settings Home'.", { ids: { settings_homepage: 88 } }), 'Set homepage to page ID 88 (AI Eval Settings Home).');
});

test('media workflow names the existing page and uploaded image', () => {
  const prompt = resolveEvalPrompt('inserting-media-into-existing-page-32', 'Insert the media into an existing page.', { pageId: 52 });
  assert.match(prompt, /page.*52/);
  assert.match(prompt, /media-flow\.png/);
  assert.match(prompt, /content/);
});
