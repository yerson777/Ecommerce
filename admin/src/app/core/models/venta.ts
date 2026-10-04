import type { Cliente } from './cliente';

export type EstadoVenta = 'pendiente' | 'parcial' | 'pagada' | 'anulada';

export interface ComprobanteVenta {
  nombre: string;
  url: string;
  mime: string;
  tamano: number;
  subido_en: string | null;
}

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
  descuento?: string;
  cupon?: string | null;
  fecha_pedido: string;
  notas: string | null;
}

export interface PagoVenta {
  id: number;
  numero_pago: string;
  monto: string;
  referencia: string | null;
  estado: string;
  pagado_en: string | null;
  nota: string | null;
  excedente: boolean;
  metodo_pago?: string | null;
  metodo_pago_id?: number | null;
  comprobante?: ComprobanteVenta | null;
}

export interface Venta {
  id: number;
  numero_venta: string;
  subtotal: string;
  costo_envio: string;
  total: string;
  estado: EstadoVenta;
  descuento?: string | null;
  anulada_en?: string | null;
  metodo_pago?: string | null;
  total_pagado: string;
  fecha_venta: string;
  notas: string | null;
  cliente?: Cliente | null;
  pedido?: PedidoInfo | null;
  pedido_id?: number | null;
  pagos?: PagoVenta[] | null;
  items?: VentaItem[] | null;
  created_at?: string;
}

export interface VentaPresencialPayload {
  cliente_id: number;
  productos: number[];
  metodo_entrega_id: number;
  metodo_pago_id: number;
  cupon_codigo?: string | null;
  descuento?: number | null;
  monto_pagado?: number | null;
  referencia?: string | null;
  notas?: string | null;
}

export interface VentaFiltros {
  busqueda?: string;
  estado?: EstadoVenta | null;
  categoria_id?: number | null;
  producto_id?: number | null;
  cliente_id?: number | null;
  metodo_pago_id?: number | null;
  fecha_desde?: string | null;
  fecha_hasta?: string | null;
  page?: number;
  per_page?: number;
}

export const ETIQUETA_ESTADO_VENTA: Record<EstadoVenta, string> = {
  pendiente: 'Pendiente',
  parcial: 'Parcial',
  pagada: 'Pagada',
  anulada: 'Anulada',
};

export const TONO_ESTADO_VENTA: Record<EstadoVenta, string> = {
  pendiente: 'warning',
  parcial: 'info',
  pagada: 'success',
  anulada: 'neutral',
};

export interface ClienteOpcion {
  id: number;
  nombre: string;
  telefono: string | null;
}