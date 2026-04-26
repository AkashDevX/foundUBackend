/**
 * Laravel-style JSON errors (422) → single user-facing string for Alert.alert().
 */

export function formatApiErrorBody(body: unknown): string {
  if (body === null || body === undefined) {
    return 'Request failed.';
  }
  if (typeof body === 'string') {
    return body;
  }
  if (typeof body !== 'object') {
    return 'Request failed.';
  }
  const b = body as Record<string, unknown>;
  if (typeof b.message === 'string' && b.message !== '') {
    return b.message;
  }
  const errors = b.errors;
  if (typeof errors === 'object' && errors !== null) {
    const lines: string[] = [];
    for (const [, msgs] of Object.entries(errors)) {
      if (Array.isArray(msgs)) {
        for (const m of msgs) {
          if (typeof m === 'string') {
            lines.push(m);
          }
        }
      }
    }
    if (lines.length > 0) {
      return lines.join('\n');
    }
  }
  return 'Request failed.';
}
