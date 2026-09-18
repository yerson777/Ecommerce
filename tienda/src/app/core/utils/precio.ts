export function formatearPrecio(valor: number | string): string {
  const numero = typeof valor === 'string' ? Number(valor) : valor;
  if (Number.isNaN(numero)) {
    return '$0.00';
  }
  return `$${numero.toFixed(2)}`;
}