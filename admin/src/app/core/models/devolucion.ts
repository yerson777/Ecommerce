export type EstadoDevolucion = 'pendiente' | 'aprobada';

export interface DevolucionPrecioPedido {
  id: number;
  numero_pedido: string;
  estado: string;
  total: string;
  fecha_pedido: string;
  cliente?: { id: number; nombre: string; telefono: string | null } | null;
}

export interface DevolucionPrenda {
  producto_id: number;
  codigo?: string | null;
  nombre?: string | null;
  talla?: string | null;
  precio_unitario: string;
}

export interface Devolucion {
  id: number;
  pedido_id: number;
  estado: EstadoDevolucion;
  monto_reembolsado: string;
  motivo: string;
  devuelto_en: string | null;
  created_at: string;
  pedido?: DevolucionPrecioPedido | null;
  venta?: { id: number; numero_venta: string; total: string } | null;
  prendas?: DevolucionPrenda[];
}

export interface VentaDisponible {
  id: number;
  numero_venta: string;
  fecha_venta: string | null;
  total: string;
  pedido_id: number | null;
  cliente_id: number | null;
  cliente?: { id: number; nombre: string; telefono: string | null } | null;
}

export interface DevolucionFiltros {
  busqueda?: string;
  estado?: EstadoDevolucion | '';
  page?: number;
  per_page?: number;
}

export interface DevolucionPayload {
  venta_id: number;
  motivo: string;
  monto_reembolso?: number | null;
}

export const ETIQUETA_ESTADO_DEVOLUCION: Record<EstadoDevolucion, string> = {
  pendiente: 'Pendiente',
  aprobada: 'Aprobada',
};

export const TONO_ESTADO_DEVOLUCION: Record<EstadoDevolucion, string> = {
  pendiente: 'warning',
  aprobada: 'success',
};