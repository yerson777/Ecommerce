import type { Cliente } from './cliente';
import type { Producto } from './producto';

export type EstadoVenta = 'pendiente' | 'parcial' | 'pagada';

export interface VentaItem {
  id: number;
  producto_id: number;
  producto_codigo?: string;
  producto_nombre?: string;
  precio_unitario: string;
}

export interface PedidoInfo {
  id: number;
  numero_pedido: string;
  estado: string;
  subtotal: string;
  costo_envio: string;
  total: string;
  fecha_pedido: string;
  notas: string | null;
}

export interface Pago {
  id: number;
  numero_pago: string;
  monto: string;
  estado: string;
  referencia: string | null;
  pagado_en: string | null;
  metodo_pago?: string | null;
}

export interface Venta {
  id: number;
  numero_venta: string;
  subtotal: string;
  costo_envio: string;
  total: string;
  estado: EstadoVenta;
  total_pagado: string;
  fecha_venta: string;
  notas: string | null;
  cliente?: Cliente | null;
  pedido?: PedidoInfo | null;
  pedido_id?: number | null;
  pagos?: Pago[] | null;
  items?: VentaItem[] | null;
  created_at?: string;
}

export interface VentaFiltros {
  busqueda?: string;
  estado?: EstadoVenta | null;
  categoria_id?: number | null;
  producto_id?: number | null;
  fecha_desde?: string | null;
  fecha_hasta?: string | null;
  page?: number;
  per_page?: number;
}

export const ETIQUETA_ESTADO_VENTA: Record<EstadoVenta, string> = {
  pendiente: 'Pendiente',
  parcial: 'Parcial',
  pagada: 'Pagada',
};

export const TONO_ESTADO_VENTA: Record<EstadoVenta, string> = {
  pendiente: 'warning',
  parcial: 'info',
  pagada: 'success',
};