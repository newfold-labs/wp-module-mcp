/**
 * OpenAI / Cloudflare chat completions reject tool parameter schemas with
 * oneOf, anyOf, allOf, enum, or not at the root. Sanitize MCP ability schemas
 * before sending tools to the eval gateway.
 */

const ROOT_FORBIDDEN = new Set(['oneOf', 'anyOf', 'allOf', 'not', 'enum']);

export function sanitizeParametersForOpenAi(
  schema: Record<string, unknown>,
  toolName?: string
): Record<string, unknown> {
  const copy = structuredClone(schema) as Record<string, unknown>;
  const changed = sanitizeNode(copy, true);
  if (changed && toolName) {
    console.warn(
      `Sanitized parameters schema for ${toolName} (removed unsupported JSON Schema keywords for OpenAI)`
    );
  }
  return copy;
}

function sanitizeNode(node: Record<string, unknown>, isRoot: boolean): boolean {
  let changed = false;

  if (isRoot) {
    for (const key of ROOT_FORBIDDEN) {
      if (key in node) {
        delete node[key];
        changed = true;
      }
    }
    if (node.type !== 'object') {
      node.type = 'object';
      changed = true;
    }
    if (node.properties === undefined) {
      node.properties = {};
      changed = true;
    }
  } else {
    for (const key of ['oneOf', 'anyOf', 'allOf', 'not'] as const) {
      if (key in node) {
        mergeFirstBranch(node, key);
        changed = true;
      }
    }
  }

  const props = node.properties;
  if (props && typeof props === 'object' && !Array.isArray(props)) {
    for (const [key, value] of Object.entries(props as Record<string, unknown>)) {
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        if (sanitizeNode(value as Record<string, unknown>, false)) {
          changed = true;
        }
      } else {
        (props as Record<string, unknown>)[key] = { type: 'string' };
        changed = true;
      }
    }
  }

  if (node.items && typeof node.items === 'object' && !Array.isArray(node.items)) {
    if (sanitizeNode(node.items as Record<string, unknown>, false)) {
      changed = true;
    }
  }

  if (node.additionalProperties && typeof node.additionalProperties === 'object') {
    if (sanitizeNode(node.additionalProperties as Record<string, unknown>, false)) {
      changed = true;
    }
  }

  return changed;
}

function mergeFirstBranch(node: Record<string, unknown>, key: 'oneOf' | 'anyOf' | 'allOf' | 'not'): void {
  const branches = node[key];
  delete node[key];
  if (Array.isArray(branches) && branches.length > 0) {
    const first = branches[0];
    if (first && typeof first === 'object' && !Array.isArray(first)) {
      const branch = first as Record<string, unknown>;
      for (const [k, v] of Object.entries(branch)) {
        if (!(k in node)) {
          node[k] = v;
        }
      }
    }
  }
  if (!node.type) {
    node.type = 'object';
  }
}
