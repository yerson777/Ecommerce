export type EstadoCompra = 'activa' | 'anulada';

export interface CompraProductoDetalle {
  id: number;
  producto_id: number;
  costo_asignado: string;
  ganancia_potencial: string;
  producto: {
    id: number;
    codigo: string;
    nombre: string;
    estado: string;
    precio: string;
    color: string | null;
    talla: string | null;
    imagen: string | null;
  } | null;
}

export interface Compra {
  id: number;
  proveedor_id: number | null;
  proveedor?: { id: number; nombre: string } | null;
  fecha: string;
  costo_total: string;
  gastos_extra: string;
  prendas_total: number;
  prendas_descartadas: number;
  prendas_vendibles: number;
  costo_repartible: string;
  nota: string | null;
  estado: EstadoCompra;
  anulada_en: string | null;
  prendas_count?: number;
  vendidas_count?: number;
  recuperado?: string;
  falta_recuperar?: string;
  productos?: CompraProductoDetalle[];
  detalle_precios?: CompraProductoDetalle[];
  created_at?: string;
  updated_at?: string;
}

export interface CompraPrendaItem {
  producto_id: number;
  costo_asignado?: number | null;
}

export interface CompraDatos {
  proveedor_id: number | null;
  fecha: string;
  costo_total: number;
  gastos_extra: number;
  prendas_total: number;
  prendas_descartadas: number;
  nota?: string | null;
  productos: CompraPrendaItem[];
}

export interface CompraFiltros {
  fecha_desde?: string;
  fecha_hasta?: string;
  proveedor_id?: number | null;
  estado?: EstadoCompra | '';
  page?: number;
  per_page?: number;
}

export const ESTADO_COMPRA_ETIQUETAS: Record<EstadoCompra, string> = {
  activa: 'Activa',
  anulada: 'Anulada',
};

export const TONO_ESTADO_COMPRA: Record<EstadoCompra, 'success' | 'neutral'> = {
  activa: 'success',
  anulada: 'neutral',
};
