export interface CompletionUsagePayload {
  prompt_tokens?: number;
  completion_tokens?: number;
  total_tokens?: number;
  prompt_tokens_details?: {
    cached_tokens?: number;
  };
  input_tokens?: number;
  output_tokens?: number;
  cache_read_input_tokens?: number;
}

export interface ModelPricingPerMillion {
  input: number;
  cachedInput?: number;
  output: number;
}

export interface EvalUsage {
  promptTokens: number;
  cachedTokens: number;
  completionTokens: number;
  totalTokens: number;
  gatewayCacheHits: number;
  /** Estimated USD across billed turns, or null when model pricing is unknown. */
  estimatedCostUsd: number | null;
}

/**
 * Standard per-1M-token USD rates for models commonly routed through Cloudflare AI Gateway.
 * Override for custom/unlisted models via EVAL_INPUT_COST_PER_1M,
 * EVAL_CACHED_INPUT_COST_PER_1M, and EVAL_OUTPUT_COST_PER_1M.
 */
const MODEL_PRICING_PER_1M: Record<string, ModelPricingPerMillion> = {
  'gpt-5-mini': { input: 0.25, cachedInput: 0.025, output: 2.0 },
  'gpt-5-nano': { input: 0.05, cachedInput: 0.005, output: 0.4 },
  'gpt-5': { input: 1.25, cachedInput: 0.125, output: 10.0 },
  'gpt-5-pro': { input: 15.0, output: 120.0 },
  'gpt-5.1': { input: 1.25, cachedInput: 0.125, output: 10.0 },
  'gpt-5.2': { input: 1.75, cachedInput: 0.175, output: 14.0 },
  'gpt-5.2-pro': { input: 21.0, output: 168.0 },
  'gpt-5.4': { input: 2.5, cachedInput: 0.25, output: 15.0 },
  'gpt-5.4-mini': { input: 0.75, cachedInput: 0.075, output: 4.5 },
  'gpt-5.4-nano': { input: 0.2, cachedInput: 0.02, output: 1.25 },
  'gpt-5.4-pro': { input: 30.0, output: 180.0 },
  'gpt-4.1': { input: 2.0, cachedInput: 0.5, output: 8.0 },
  'gpt-4.1-mini': { input: 0.4, cachedInput: 0.1, output: 1.6 },
  'gpt-4.1-nano': { input: 0.1, cachedInput: 0.025, output: 0.4 },
  'gpt-4o': { input: 2.5, cachedInput: 1.25, output: 10.0 },
  'gpt-4o-mini': { input: 0.15, cachedInput: 0.075, output: 0.6 },
  o3: { input: 2.0, cachedInput: 0.5, output: 8.0 },
  'o3-mini': { input: 1.1, cachedInput: 0.55, output: 4.4 },
  'o4-mini': { input: 1.1, cachedInput: 0.275, output: 4.4 },
  'claude-sonnet-4': { input: 3.0, cachedInput: 0.3, output: 15.0 },
  'claude-sonnet-4-5': { input: 3.0, cachedInput: 0.3, output: 15.0 },
  'claude-sonnet-4-6': { input: 3.0, cachedInput: 0.3, output: 15.0 },
  'claude-haiku-4-5': { input: 1.0, cachedInput: 0.1, output: 5.0 },
  'claude-3-5-haiku': { input: 0.8, cachedInput: 0.08, output: 4.0 },
  'gemini-2.5-flash': { input: 0.3, cachedInput: 0.03, output: 2.5 },
  'gemini-2.5-pro': { input: 1.25, cachedInput: 0.125, output: 10.0 },
};

function nonNegativeInt(value: unknown): number {
  return typeof value === 'number' && Number.isFinite(value) && value > 0
    ? Math.floor(value)
    : 0;
}

function parseNonNegativeRate(raw: string | undefined): number | undefined {
  if (raw === undefined || raw.trim() === '') return undefined;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : undefined;
}

export function normalizeModelPricingKey(model: string): string {
  const trimmed = model.trim().toLowerCase();
  const withoutProvider = trimmed.includes('/')
    ? trimmed.slice(trimmed.indexOf('/') + 1)
    : trimmed;
  return withoutProvider.replace(/-(?:\d{4}-\d{2}-\d{2}|\d{8})$/, '');
}

export function resolveModelPricing(model: string): ModelPricingPerMillion | undefined {
  const envInput = parseNonNegativeRate(process.env.EVAL_INPUT_COST_PER_1M);
  const envOutput = parseNonNegativeRate(process.env.EVAL_OUTPUT_COST_PER_1M);
  if (envInput !== undefined && envOutput !== undefined) {
    const envCached = parseNonNegativeRate(process.env.EVAL_CACHED_INPUT_COST_PER_1M);
    return {
      input: envInput,
      cachedInput: envCached,
      output: envOutput,
    };
  }

  if (!model) return undefined;
  const normalized = normalizeModelPricingKey(model);
  const withoutProvider = model.trim().toLowerCase().replace(/^[^/]+\//, '');
  return MODEL_PRICING_PER_1M[withoutProvider] ?? MODEL_PRICING_PER_1M[normalized];
}

export function createEmptyUsage(defaultModel?: string): EvalUsage {
  return {
    promptTokens: 0,
    cachedTokens: 0,
    completionTokens: 0,
    totalTokens: 0,
    gatewayCacheHits: 0,
    estimatedCostUsd: defaultModel && resolveModelPricing(defaultModel) ? 0 : null,
  };
}

export function addTurnUsage(
  current: EvalUsage,
  turnUsage: CompletionUsagePayload | undefined,
  model: string,
  gatewayCacheHit = false
): EvalUsage {
  const promptTokens = nonNegativeInt(turnUsage?.prompt_tokens ?? turnUsage?.input_tokens);
  const completionTokens = nonNegativeInt(
    turnUsage?.completion_tokens ?? turnUsage?.output_tokens
  );
  const cachedTokens = Math.min(
    promptTokens,
    nonNegativeInt(
      turnUsage?.prompt_tokens_details?.cached_tokens ?? turnUsage?.cache_read_input_tokens
    )
  );
  const uncachedPromptTokens = promptTokens - cachedTokens;
  const totalTokens =
    turnUsage?.total_tokens !== undefined
      ? nonNegativeInt(turnUsage.total_tokens)
      : promptTokens + completionTokens;

  let turnCost: number | null;
  if (gatewayCacheHit) {
    turnCost = 0;
  } else {
    const pricing = resolveModelPricing(model);
    if (pricing) {
      const cachedRate = pricing.cachedInput ?? pricing.input;
      turnCost =
        (uncachedPromptTokens * pricing.input +
          cachedTokens * cachedRate +
          completionTokens * pricing.output) /
        1_000_000;
    } else if (totalTokens === 0) {
      turnCost = current.estimatedCostUsd !== null ? 0 : null;
    } else {
      turnCost = null;
    }
  }

  const hadPriorActivity = current.totalTokens > 0 || current.gatewayCacheHits > 0;
  let estimatedCostUsd: number | null;
  if (!hadPriorActivity) {
    estimatedCostUsd = turnCost !== null ? (current.estimatedCostUsd ?? 0) + turnCost : null;
  } else if (current.estimatedCostUsd === null || turnCost === null) {
    estimatedCostUsd = null;
  } else {
    estimatedCostUsd = current.estimatedCostUsd + turnCost;
  }

  return {
    promptTokens: current.promptTokens + promptTokens,
    cachedTokens: current.cachedTokens + cachedTokens,
    completionTokens: current.completionTokens + completionTokens,
    totalTokens: current.totalTokens + totalTokens,
    gatewayCacheHits: current.gatewayCacheHits + (gatewayCacheHit ? 1 : 0),
    estimatedCostUsd,
  };
}

export function mergeEvalUsage(a: EvalUsage, b: EvalUsage): EvalUsage {
  const aActive = a.totalTokens > 0 || a.gatewayCacheHits > 0;
  const bActive = b.totalTokens > 0 || b.gatewayCacheHits > 0;

  let estimatedCostUsd: number | null;
  if (aActive && bActive) {
    estimatedCostUsd =
      a.estimatedCostUsd !== null && b.estimatedCostUsd !== null
        ? a.estimatedCostUsd + b.estimatedCostUsd
        : null;
  } else if (aActive) {
    estimatedCostUsd = a.estimatedCostUsd;
  } else if (bActive) {
    estimatedCostUsd = b.estimatedCostUsd;
  } else {
    estimatedCostUsd = a.estimatedCostUsd ?? b.estimatedCostUsd;
  }

  return {
    promptTokens: a.promptTokens + b.promptTokens,
    cachedTokens: a.cachedTokens + b.cachedTokens,
    completionTokens: a.completionTokens + b.completionTokens,
    totalTokens: a.totalTokens + b.totalTokens,
    gatewayCacheHits: a.gatewayCacheHits + b.gatewayCacheHits,
    estimatedCostUsd,
  };
}

export function formatUsd(usd: number): string {
  if (usd <= 0) return '$0.00';
  if (usd < 0.0001) return '<$0.0001';
  if (usd < 1) {
    const rounded = Math.round((usd + Number.EPSILON) * 10_000) / 10_000;
    return `$${rounded.toFixed(4)}`;
  }
  const rounded = Math.round((usd + Number.EPSILON) * 100) / 100;
  return `$${rounded.toFixed(2)}`;
}

export function formatTokensCell(usage?: EvalUsage): string {
  if (!usage || (usage.totalTokens === 0 && usage.gatewayCacheHits === 0)) {
    return '—';
  }
  const tokens = usage.totalTokens.toLocaleString('en-US');
  const cost =
    usage.estimatedCostUsd !== null ? ` (${formatUsd(usage.estimatedCostUsd)})` : '';
  return `${tokens}${cost}`;
}

export function formatRunTokensSummary(usage: EvalUsage): string {
  const total = usage.totalTokens.toLocaleString('en-US');
  if (usage.totalTokens === 0) {
    return '0';
  }
  const prompt = usage.promptTokens.toLocaleString('en-US');
  const completion = usage.completionTokens.toLocaleString('en-US');
  const cachedPart =
    usage.cachedTokens > 0
      ? `, ${usage.cachedTokens.toLocaleString('en-US')} cached`
      : '';
  return `${total} (${prompt} in${cachedPart} / ${completion} out)`;
}
