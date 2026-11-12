import assert from 'node:assert/strict';
import test from 'node:test';
import {
  addTurnUsage,
  createEmptyUsage,
  formatRunTokensSummary,
  formatTokensCell,
  formatUsd,
  mergeEvalUsage,
  normalizeModelPricingKey,
  resolveModelPricing,
} from './usage.js';

test('normalizes provider prefixes and snapshot dates for pricing lookup', () => {
  assert.equal(normalizeModelPricingKey('openai/gpt-5-mini'), 'gpt-5-mini');
  assert.equal(normalizeModelPricingKey('openai/gpt-5-mini-2025-08-07'), 'gpt-5-mini');
  assert.equal(normalizeModelPricingKey('anthropic/claude-sonnet-4-5-20250929'), 'claude-sonnet-4-5');
  assert.deepEqual(resolveModelPricing('openai/gpt-5-mini-2025-08-07'), {
    input: 0.25,
    cachedInput: 0.025,
    output: 2.0,
  });
});

test('computes multi-turn token usage and cached-input discounted USD cost', () => {
  let usage = createEmptyUsage('openai/gpt-5-mini');
  usage = addTurnUsage(
    usage,
    {
      prompt_tokens: 2000,
      completion_tokens: 500,
      total_tokens: 2500,
      prompt_tokens_details: { cached_tokens: 1000 },
    },
    'openai/gpt-5-mini'
  );
  // Turn 1: 1000 * $0.25/1M ($0.00025) + 1000 * $0.025/1M ($0.000025) + 500 * $2.00/1M ($0.001) = $0.001275
  assert.equal(usage.promptTokens, 2000);
  assert.equal(usage.cachedTokens, 1000);
  assert.equal(usage.completionTokens, 500);
  assert.equal(usage.totalTokens, 2500);
  assert.ok(usage.estimatedCostUsd !== null);
  assert.ok(Math.abs(usage.estimatedCostUsd - 0.001275) < 1e-12);
  assert.equal(formatTokensCell(usage), '2,500 ($0.0013)');
  assert.equal(formatRunTokensSummary(usage), '2,500 (2,000 in, 1,000 cached / 500 out)');
});

test('treats Cloudflare AI Gateway cache hits as zero USD cost while preserving token counts', () => {
  let usage = createEmptyUsage('openai/gpt-4o');
  usage = addTurnUsage(
    usage,
    { prompt_tokens: 4000, completion_tokens: 200, total_tokens: 4200 },
    'openai/gpt-4o',
    true
  );
  assert.equal(usage.totalTokens, 4200);
  assert.equal(usage.gatewayCacheHits, 1);
  assert.equal(usage.estimatedCostUsd, 0);
  assert.equal(formatTokensCell(usage), '4,200 ($0.00)');
});

test('reports null cost when an unlisted model consumes tokens without env rate overrides', () => {
  let usage = createEmptyUsage('custom/mystery-model');
  assert.equal(formatTokensCell(usage), '—');
  usage = addTurnUsage(
    usage,
    { prompt_tokens: 1200, completion_tokens: 80, total_tokens: 1280 },
    'custom/mystery-model'
  );
  assert.equal(usage.totalTokens, 1280);
  assert.equal(usage.estimatedCostUsd, null);
  assert.equal(formatTokensCell(usage), '1,280');

  const mergedWithInactive = mergeEvalUsage(createEmptyUsage('custom/mystery-model'), usage);
  assert.equal(mergedWithInactive.estimatedCostUsd, null);
  assert.equal(mergedWithInactive.totalTokens, 1280);
});

test('supports environment pricing overrides and USD formatting thresholds', () => {
  const prevIn = process.env.EVAL_INPUT_COST_PER_1M;
  const prevCached = process.env.EVAL_CACHED_INPUT_COST_PER_1M;
  const prevOut = process.env.EVAL_OUTPUT_COST_PER_1M;
  process.env.EVAL_INPUT_COST_PER_1M = '1.5';
  process.env.EVAL_CACHED_INPUT_COST_PER_1M = '0.15';
  process.env.EVAL_OUTPUT_COST_PER_1M = '6';
  try {
    const usage = addTurnUsage(
      createEmptyUsage('custom/model'),
      { prompt_tokens: 1000, completion_tokens: 1000, prompt_tokens_details: { cached_tokens: 500 } },
      'custom/model'
    );
    // 500 * 1.5/1M (0.00075) + 500 * 0.15/1M (0.000075) + 1000 * 6/1M (0.006) = 0.006825
    assert.ok(usage.estimatedCostUsd !== null);
    assert.ok(Math.abs(usage.estimatedCostUsd - 0.006825) < 1e-12);
    assert.equal(formatUsd(0), '$0.00');
    assert.equal(formatUsd(0.00004), '<$0.0001');
    assert.equal(formatUsd(0.0412), '$0.0412');
    assert.equal(formatUsd(1.345), '$1.35');
  } finally {
    if (prevIn === undefined) delete process.env.EVAL_INPUT_COST_PER_1M;
    else process.env.EVAL_INPUT_COST_PER_1M = prevIn;
    if (prevCached === undefined) delete process.env.EVAL_CACHED_INPUT_COST_PER_1M;
    else process.env.EVAL_CACHED_INPUT_COST_PER_1M = prevCached;
    if (prevOut === undefined) delete process.env.EVAL_OUTPUT_COST_PER_1M;
    else process.env.EVAL_OUTPUT_COST_PER_1M = prevOut;
  }
});
