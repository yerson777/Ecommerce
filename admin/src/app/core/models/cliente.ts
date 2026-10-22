import type { Pedido } from './pedido';
import type { Venta } from './venta';
import type { Reserva } from './reserva';
import type { Devolucion } from './devolucion';

export interface UltimoPedidoCliente {
  numero_pedido: string;
  estado: string;
  total: string;
  fecha_pedido: string;
}

export interface Cliente {
  id: number;
  nombre: string;
  telefono: string | null;
  email: string | null;
  direccion: string | null;
  ciudad?: string | null;
  notas: string | null;
  fecha_primer_pedido?: string | null;
  fecha_ultimo_pedido?: string | null;
  bloqueado?: boolean;
  motivo_bloqueo?: string | null;
  es_prueba?: boolean;
  es_duplicado?: boolean;
  pedidos_count?: number;
  compras_count?: number;
  ventas_count?: number;
  total_comprado?: string | null;
  ticket_promedio?: string | null;
  saldo_pendiente?: string | null;
  ultimo_pedido?: UltimoPedidoCliente | null;
  pedidos?: Pedido[] | null;
  ventas?: Venta[] | null;
  reservas?: Reserva[] | null;
  devoluciones?: Devolucion[] | null;
  created_at?: string;
}

export type ClienteFiltro = '' | 'nuevos' | 'recurrentes' | 'pendientes' | 'bloqueados';

export interface ClienteFiltros {
  busqueda?: string;
  filtro?: ClienteFiltro | null;
  page?: number;
  per_page?: number;
}

export interface ClientePayload {
  nombre: string;
  telefono?: string | null;
  email?: string | null;
  direccion?: string | null;
  ciudad?: string | null;
  notas?: string | null;
  bloqueado?: boolean;
  motivo_bloqueo?: string | null;
  es_prueba?: boolean;
  confirmar_duplicado?: boolean;
}

export interface ClienteDuplicadoRef {
  id: number;
  nombre: string;
  telefono: string | null;
  email: string | null;
  ciudad: string | null;
  bloqueado: boolean;
  pedidos_count: number;
  fecha_ultimo_pedido: string | null;
}

export interface UnirClientesPayload {
  principal_id: number;
  duplicado_ids: number[];
}

export const ETIQUETA_FILTRO_CLIENTE: Record<Exclude<ClienteFiltro, ''>, string> = {
  nuevos: 'Nuevos',
  recurrentes: 'Recurrentes',
  pendientes: 'Con pendientes',
  bloqueados: 'Bloqueados',
};
