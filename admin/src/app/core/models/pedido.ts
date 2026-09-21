import type { Cliente } from './cliente';

export type EstadoPedido = 'pendiente' | 'confirmado' | 'cancelado' | 'completado' | 'devuelto';

export type EstadoPagoPedido = 'pendiente' | 'parcial' | 'pagado' | 'cancelado' | 'reembolsado';

export interface Devolucion {
  motivo: string;
  monto_reembolsado: string;
  devuelto_en: string | null;
}

export interface PedidoItem {
  id: number;
  producto_id: number;
  producto_codigo?: string;
  producto_nombre?: string;
  producto_talla?: string | null;
  producto_color?: string | null;
  producto_imagen?: string | null;
  precio_unitario: string;
}

export interface Pedido {
  id: number;
  numero_pedido: string;
  estado: EstadoPedido;
  estados_siguientes: EstadoPedido[];
  subtotal: string;
  costo_envio: string;
  total: string;
  fecha_pedido: string;
  notas: string | null;
  comprobante_url?: string | null;
  cliente?: Cliente | null;
  metodo_pago?: string | null;
  metodo_entrega?: string | null;
  items?: PedidoItem[] | null;
  numero_venta?: string | null;
  estado_pago?: EstadoPagoPedido | null;
  total_pagado?: string | null;
  saldo_pendiente?: string | null;
  devolucion?: Devolucion | null;
  created_at?: string | null;
}

export interface PedidoFiltros {
  busqueda?: string;
  estado?: EstadoPedido | null;
  fecha_desde?: string | null;
  fecha_hasta?: string | null;
  page?: number;
  per_page?: number;
}

export const ETIQUETA_ESTADO_PEDIDO: Record<EstadoPedido, string> = {
  pendiente: 'Pendiente',
  confirmado: 'Confirmado',
  cancelado: 'Cancelado',
  completado: 'Completado',
  devuelto: 'Devuelto',
};

export const TONO_ESTADO_PEDIDO: Record<EstadoPedido, string> = {
  pendiente: 'warning',
  confirmado: 'info',
  cancelado: 'danger',
  completado: 'success',
  devuelto: 'neutral',
};

export const ETIQUETA_ESTADO_PAGO_PEDIDO: Record<EstadoPagoPedido, string> = {
  pendiente: 'Pendiente',
  parcial: 'Parcial',
  pagado: 'Pagado',
  cancelado: 'Cancelado',
  reembolsado: 'Reembolsado',
};

export const TONO_ESTADO_PAGO_PEDIDO: Record<EstadoPagoPedido, string> = {
  pendiente: 'warning',
  parcial: 'info',
  pagado: 'success',
  cancelado: 'danger',
  reembolsado: 'neutral',
};