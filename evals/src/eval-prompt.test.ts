import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveEvalPrompt } from './eval-prompt.js';

test('supplies file bytes for upload cases and an actual seeded product ID', () => {
  const upload = resolveEvalPrompt('uploading-media-file-28', "Upload a media file named 'test.jpg'.", {});
  assert.match(upload, /data:image\/png;base64,/);
  assert.match(upload, /test.png/);
  const product = resolveEvalPrompt('wc-product-get-1', 'Show product ID 42', { productId: 123 });
  assert.equal(product, 'Show product ID 123');
});

test('does not pretend an unseeded product ID exists', () => {
  assert.throws(() => resolveEvalPrompt('wc-product-get-1', 'Show product ID 42', {}), /product fixture/i);
});
