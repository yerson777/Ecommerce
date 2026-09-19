export interface ImagenPublica {
  id: number;
  url: string;
  es_principal: boolean;
  orden: number;
}

export interface CategoriaRef {
  id: number;
  nombre: string;
  slug: string;
}

export interface TallaRef {
  id: number;
  nombre: string;
}

export type EstadoProducto = 'disponible' | 'reservada' | 'vendida';

export interface ProductoPublico {
  id: number;
  codigo: string;
  nombre: string;
  color: string | null;
  descripcion: string | null;
  precio: string;
  estado: EstadoProducto;
  es_nuevo: boolean;
  categoria: CategoriaRef | null;
  talla: TallaRef | null;
  imagenes: ImagenPublica[];
}