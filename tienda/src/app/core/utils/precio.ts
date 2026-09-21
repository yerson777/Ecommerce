export function formatearPrecio(valor: number | string): string {
  const numero = typeof valor === 'string' ? Number(valor) : valor;
  if (Number.isNaN(numero)) {
    return 'Bs 0.00';
  }
  return `Bs ${numero.toFixed(2)}`;
}