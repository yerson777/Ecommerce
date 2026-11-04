const ETIQUETAS_ESTADO: Record<string, string> = {
  pendiente: 'Pendiente',
  confirmado: 'Confirmado',
  completado: 'Completado',
  cancelado: 'Cancelado',
  devuelto: 'Devuelto',
};

const ETIQUETAS_PAGO: Record<string, string> = {
  pendiente: 'Sin pagar',
  parcial: 'Pago parcial',
  pagado: 'Pagado',
  cancelado: 'Cancelado',
  reembolsado: 'Reembolsado',
};

export function formatearFecha(fecha: string | null | undefined): string {
  if (!fecha) {
    return '—';
  }

  const [anio, mes, dia] = fecha.slice(0, 10).split('-');
  return `${dia}/${mes}/${anio}`;
}

export function etiquetaEstadoPedido(estado: string): string {
  return ETIQUETAS_ESTADO[estado] ?? estado;
}

export function etiquetaEstadoPago(estado: string): string {
  return ETIQUETAS_PAGO[estado] ?? estado;
}
