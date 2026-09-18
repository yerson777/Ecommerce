export type EstadoPrenda = 'disponible' | 'reservada' | 'vendida';

export interface CategoriaRef {
  id: number;
  nombre: string;
  slug?: string;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
}

export interface TallaRef {
  id: number;
  nombre: string;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
}

export interface ProductoImagen {
  id: number;
  producto_id: number;
  ruta: string;
  url: string;
  nombre_original: string | null;
  es_principal: boolean;
  orden: number;
  created_at?: string;
  updated_at?: string;
}

export interface Producto {
  id: number;
  codigo: string;
  nombre: string;
  color: string | null;
  descripcion: string | null;
  costo: string;
  precio: string;
  margen: string;
  estado: EstadoPrenda;
  publicado: boolean;
  fecha_ingreso: string | null;
  categoria?: CategoriaRef | null;
  talla?: TallaRef | null;
  imagenes?: ProductoImagen[];
  created_at?: string;
  updated_at?: string;
}

export interface ProductoPayload {
  codigo: string;
  nombre: string;
  categoria_id: number;
  talla_id: number;
  color?: string | null;
  descripcion?: string | null;
  costo: number;
  precio: number;
  estado?: 'disponible';
  publicado: boolean;
  fecha_ingreso?: string | null;
}

export interface ProductoFiltros {
  busqueda?: string;
  categoria?: number | null;
  talla?: number | null;
  estado?: EstadoPrenda | null;
  publicado?: boolean | null;
  precio_min?: number | null;
  precio_max?: number | null;
  page?: number;
  per_page?: number;
}

export interface ProductoHistorialEntry {
  id: number;
  producto_id: number;
  evento: string;
  estado_anterior: string | null;
  estado_nuevo: string | null;
  detalle: string | null;
  user_id: number | null;
  created_at: string;
}

export const ETIQUETA_ESTADO: Record<EstadoPrenda, string> = {
  disponible: 'Disponible',
  reservada: 'Reservada',
  vendida: 'Vendida',
};

export const TONO_ESTADO: Record<EstadoPrenda, string> = {
  disponible: 'success',
  reservada: 'warning',
  vendida: 'danger',
};