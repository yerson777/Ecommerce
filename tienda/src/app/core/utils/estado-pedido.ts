import { PedidoPublico } from '../models/pedido';

export type VistaEstadoPedido = 'confirmacion' | 'seguimiento';

export interface CartelEstadoPedido {
  texto: string;
  clase: string;
}

export function pedidoPagado(pedido: PedidoPublico | null | undefined): boolean {
  if (!pedido) {
    return false;
  }
  if (pedido.estado_pago) {
    return pedido.estado_pago === 'pagado';
  }
  const total = parseFloat(String(pedido.total ?? '0'));
  const pagado = parseFloat(String(pedido.total_pagado ?? '0'));
  return total > 0 && pagado >= total - 0.01;
}

export function cartelEstadoPedido(
  estado: string,
  pagado: boolean,
  vista: VistaEstadoPedido,
): CartelEstadoPedido {
  const confirmacion = vista === 'confirmacion';
  const naranja = 'badge badge-pedido-recibido';
  const verde = confirmacion ? 'badge badge-pago-confirmado' : 'badge badge-success';
  const neutral = 'badge badge-neutral';

  switch (estado) {
    case 'pendiente':
      return { texto: 'Pedido recibido · Pago en revisión', clase: naranja };
    case 'confirmado':
      return pagado
        ? { texto: 'Pago confirmado', clase: verde }
        : { texto: 'Pedido confirmado · Pendiente de pago', clase: naranja };
    case 'completado':
      return pagado
        ? { texto: '¡Pago recibido! Compra completada', clase: verde }
        : { texto: 'Compra completada · Pago pendiente', clase: naranja };
    case 'cancelado':
      return { texto: 'Cancelado', clase: confirmacion ? naranja : neutral };
    case 'devuelto':
      return confirmacion
        ? { texto: 'Devuelto', clase: naranja }
        : { texto: estado, clase: neutral };
    default:
      return { texto: estado, clase: confirmacion ? naranja : neutral };
  }
}
