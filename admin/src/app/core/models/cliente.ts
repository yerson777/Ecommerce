import type { Pedido } from './pedido';

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
  pedidos_count?: number;
  total_comprado?: string | null;
  ultimo_pedido?: UltimoPedidoCliente | null;
  pedidos?: Pedido[] | null;
  created_at?: string;
}

export interface ClienteFiltros {
  busqueda?: string;
  page?: number;
  per_page?: number;
}