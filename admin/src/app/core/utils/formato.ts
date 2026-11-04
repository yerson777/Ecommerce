/**
 * Formateadores compartidos del panel admin.
 * Mantienen el mismo formato que usaban los componentes (Bs 0.00 y dd/mm/aaaa).
 */

export function formatearMoneda(valor: string | number | null | undefined): string {
  const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
  return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
}

export function formatearFecha(fecha: string | null | undefined): string {
  if (!fecha) {
    return '—';
  }

  const [anio, mes, dia] = fecha
    .slice(0, 10)
    .split('-')
    .map((parte) => Number(parte));

  return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
}
