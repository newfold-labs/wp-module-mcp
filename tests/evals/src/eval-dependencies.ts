export function blockingPrerequisite(
  requiredIds: string[] | undefined,
  statuses: Map<string, string>
): string | undefined {
  return requiredIds?.find((id) => statuses.has(id) && statuses.get(id) !== 'pass');
}
