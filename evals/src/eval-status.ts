import type { AgentEvalResult } from './agent-loop.js';

export type EvalStatus = 'pass' | 'warning' | 'fail' | 'error';

export function classifyEvalResult(result: Pick<AgentEvalResult, 'matched' | 'restAlternative' | 'error' | 'executionFailed'>): EvalStatus {
  if (result.executionFailed) return 'warning';
  if (result.matched) return 'pass';
  if (result.error) return 'error';
  if (result.restAlternative) return 'warning';
  return 'fail';
}
