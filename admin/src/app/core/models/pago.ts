import type { EstadoPagoPedido } from './pedido';

export type EstadoPago = 'pendiente' | 'completado' | 'anulado' | 'reembolsado';

export interface ComprobanteInfo {
  nombre: string;
  url: string;
  mime: string;
  tamano: number;
  subido_en: string | null;
}

export interface PagoHistorial {
  id: number;
  numero_pago: string;
  monto: string;
  metodo_pago: string | null;
  referencia: string | null;
  estado: EstadoPago;
  pagado_en: string | null;
  nota: string | null;
  comprobante: ComprobanteInfo | null;
}

export interface PagoPedido {
  id: number;
  numero_pedido: string;
  estado: string;
  total: string;
  total_pagado: string;
  saldo_pendiente: string;
  estado_pago: EstadoPagoPedido;
  metodo_pago: string | null;
  cliente: string | null;
  pagos: PagoHistorial[];
}

export interface PagoVenta {
  id: number;
  numero_venta: string;
  total: string;
  cliente: string | null;
}

export interface Pago {
  id: number;
  numero_pago: string;
  pedido_id: number | null;
  venta_id: number | null;
  monto: string;
  referencia: string | null;
  estado: EstadoPago;
  pagado_en: string | null;
  nota: string | null;
  excedente: boolean;
  metodo_pago: string | null;
  metodo_pago_id: number | null;
  comprobante: ComprobanteInfo | null;
  pedido: PagoPedido | null;
  venta: PagoVenta | null;
  created_at: string;
}

export interface MetodoPagoRef {
  id: number;
  nombre: string;
  activo: boolean;
}

export interface PagoFiltros {
  busqueda?: string;
  estado?: EstadoPago | null;
  metodo_pago_id?: number | null;
  fecha_desde?: string | null;
  fecha_hasta?: string | null;
  page?: number;
  per_page?: number;
}

export interface PagoPayload {
  pedido_id: number;
  monto: number;
  metodo_pago_id: number;
  fecha?: string | null;
  estado?: EstadoPago;
  referencia?: string | null;
  nota?: string | null;
  permitir_excedente?: boolean;
  comprobante?: File | null;
}

export const ETIQUETA_ESTADO_PAGO: Record<EstadoPago, string> = {
  pendiente: 'Pendiente',
  completado: 'Completado',
  anulado: 'Anulado',
  reembolsado: 'Reembolsado',
};

export const TONO_ESTADO_PAGO: Record<EstadoPago, string> = {
  pendiente: 'warning',
  completado: 'success',
  anulado: 'danger',
  reembolsado: 'neutral',
};