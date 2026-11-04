export const CLIENTE_TOKEN_KEY = 'everly_cliente_token_v1';

export function leerTokenCliente(): string | null {
  try {
    return localStorage.getItem(CLIENTE_TOKEN_KEY);
  } catch {
    return null;
  }
}

export function guardarTokenCliente(token: string): void {
  try {
    localStorage.setItem(CLIENTE_TOKEN_KEY, token);
  } catch {
    // Sin almacenamiento la sesión no persiste entre recargas.
  }
}

export function borrarTokenCliente(): void {
  try {
    localStorage.removeItem(CLIENTE_TOKEN_KEY);
  } catch {
    // Nada que limpiar.
  }
}
