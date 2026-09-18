export type Primitiva = string | number | boolean | null | undefined;

export function toQueryString(params: Record<string, Primitiva>): string {
  const partes: string[] = [];

  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '') {
      continue;
    }

    if (typeof value === 'boolean') {
      partes.push(`${encodeURIComponent(key)}=${value ? 1 : 0}`);
    } else {
      partes.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
    }
  }

  return partes.length > 0 ? `?${partes.join('&')}` : '';
}