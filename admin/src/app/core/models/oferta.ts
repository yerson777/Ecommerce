export type TipoOferta = 'porcentaje' | 'fijo';

export type EstadoOferta = 'activa' | 'programada' | 'vencida' | 'desactivada';

export type AlcanceOferta = 'prendas' | 'categorias';

export interface OfertaCategoriaRef {
  id: number;
  nombre: string;
}

export interface Oferta {
  id: number;
  nombre: string;
  tipo: TipoOferta;
  valor: string;
  fecha_inicio: string;
  fecha_fin: string;
  activo: boolean;
  permitir_cupon: boolean;
  estado: EstadoOferta;
  aplicar_a: AlcanceOferta;
  productos_ids: number[];
  productos_count: number;
  categorias: OfertaCategoriaRef[];
  ventas_count: number;
  total_descontado: string;
  created_at: string;
  updated_at: string;
}

export interface OfertaDatos {
  nombre: string;
  tipo: TipoOferta;
  valor: number;
  fecha_inicio: string;
  fecha_fin: string;
  aplicar_a: AlcanceOferta;
  productos: number[];
  categorias: number[];
  permitir_cupon: boolean;
  activo: boolean;
}

export const TIPOS_OFERTA: TipoOferta[] = ['porcentaje', 'fijo'];

export const TIPO_ETIQUETAS: Record<TipoOferta, string> = {
  porcentaje: 'Porcentaje',
  fijo: 'Monto fijo',
};

export const ESTADO_ETIQUETAS: Record<EstadoOferta, string> = {
  activa: 'Activa',
  programada: 'Programada',
  vencida: 'Vencida',
  desactivada: 'Desactivada',
};

export const TONO_ESTADO: Record<EstadoOferta, 'success' | 'info' | 'warning' | 'neutral'> = {
  activa: 'success',
  programada: 'info',
  vencida: 'warning',
  desactivada: 'neutral',
};

export const ESTADOS_OFERTA: EstadoOferta[] = ['activa', 'programada', 'vencida', 'desactivada'];
