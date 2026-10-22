export type EstadoPrenda = 'disponible' | 'reservada' | 'vendida';

export type OrdenProducto = 'recientes' | 'antiguedad' | 'precio_asc' | 'precio_desc' | 'dias';

export interface CategoriaRef {
  id: number;
  nombre: string;
  slug?: string;
  imagen?: string | null;
  imagen_url?: string | null;
  parent_id?: number | null;
  parent?: { id: number; nombre: string } | null;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
  hijos_count?: number;
}

export interface TallaRef {
  id: number;
  nombre: string;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
}

export interface MarcaRef {
  id: number;
  nombre: string;
  slug?: string;
  logo?: string | null;
  logo_url?: string | null;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
}

export interface CondicionRef {
  id: number;
  nombre: string;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
}

export interface CondicionProducto {
  id: number;
  nombre: string;
}

export interface ProductoImagen {
  id: number;
  producto_id: number;
  ruta: string;
  url: string;
  nombre_original?: string | null;
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
  condicion_id?: number | null;
  condicion?: CondicionProducto | null;
  medidas: string | null;
  descripcion: string | null;
  costo: string;
  precio: string;
  margen: string;
  estado: EstadoPrenda;
  publicado: boolean;
  oculta: boolean;
  verificada?: boolean;
  verificada_en?: string | null;
  fecha_ingreso: string | null;
  fecha_publicacion: string | null;
  dias_en_inventario: number | null;
  categoria?: CategoriaRef | null;
  talla?: TallaRef | null;
  marca?: MarcaRef | null;
  imagenes?: ProductoImagen[];
  created_at?: string;
  updated_at?: string;
}

export interface ProductoPayload {
  codigo: string;
  nombre: string;
  categoria_id: number;
  talla_id: number;
  marca_id?: number | null;
  marca_nombre?: string | null;
  color?: string | null;
  condicion_id?: number | null;
  medidas?: string | null;
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
  marca?: number | null;
  condicion?: number | null;
  estado?: EstadoPrenda | null;
  publicado?: boolean | null;
  precio_min?: number | null;
  precio_max?: number | null;
  orden?: OrdenProducto;
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
  usuario?: string | null;
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

export const ETIQUETA_ORDEN: Record<OrdenProducto, string> = {
  recientes: 'Más recientes',
  antiguedad: 'Más antiguos',
  precio_asc: 'Precio: menor a mayor',
  precio_desc: 'Precio: mayor a menor',
  dias: 'Más días en inventario',
};
