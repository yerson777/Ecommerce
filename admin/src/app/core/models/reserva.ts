export type EstadoReserva = 'activa' | 'completada' | 'anulada' | 'expirada' | 'liberada';

export interface ReservaCliente {
  id: number;
  nombre: string;
  telefono: string | null;
}

export interface ReservaPedido {
  id: number;
  numero_pedido: string;
  estado: string;
}

export interface ReservaProducto {
  id: number;
  codigo: string;
  nombre: string;
  precio: string;
  talla?: { id: number; nombre: string } | null;
  categoria?: { id: number; nombre: string } | null;
}

export interface Reserva {
  id: number;
  producto_id: number;
  pedido_id: number | null;
  estado: EstadoReserva;
  vencida: boolean;
  vence_en: string | null;
  liberada_en: string | null;
  created_at: string;
  producto?: ReservaProducto | null;
  cliente?: ReservaCliente | null;
  pedido?: ReservaPedido | null;
}

export interface ReservaFiltros {
  busqueda?: string;
  estado?: string;
  solo_vencidas?: boolean;
  page?: number;
  per_page?: number;
}

export interface ReservaPayload {
  producto_id: number;
  cliente_id: number;
  vence_en?: string | null;
  pedido_id?: number | null;
}

export interface ReservaVentaPayload {
  costo_envio?: number;
  notas?: string | null;
}

export const ETIQUETA_ESTADO_RESERVA: Record<EstadoReserva, string> = {
  activa: 'Activa',
  completada: 'Completada',
  anulada: 'Anulada',
  expirada: 'Expirada',
  liberada: 'Liberada',
};

export const TONO_ESTADO_RESERVA: Record<EstadoReserva, string> = {
  activa: 'primary',
  completada: 'info',
  anulada: 'neutral',
  expirada: 'danger',
  liberada: 'neutral',
};