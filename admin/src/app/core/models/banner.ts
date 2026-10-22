export type TipoEnlaceBanner = 'ninguno' | 'producto' | 'categoria' | 'oferta' | 'url';

export interface Banner {
  id: number;
  ruta: string;
  url: string;
  ruta_movil: string | null;
  url_movil: string | null;
  titulo: string | null;
  subtitulo: string | null;
  enlace: string | null;
  enlace_tipo: TipoEnlaceBanner;
  enlace_id: number | null;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  activo: boolean;
  vigente: boolean;
  aviso_destino: string | null;
  orden: number;
  created_at?: string;
  updated_at?: string;
}

export interface BannerPayload {
  titulo?: string | null;
  subtitulo?: string | null;
  enlace_tipo?: TipoEnlaceBanner;
  enlace_id?: number | null;
  enlace_url?: string | null;
  fecha_inicio?: string | null;
  fecha_fin?: string | null;
  sin_imagen_movil?: boolean;
  activo?: boolean;
  orden?: number;
}

export const TIPOS_ENLACE_BANNER: TipoEnlaceBanner[] = [
  'ninguno',
  'producto',
  'categoria',
  'oferta',
  'url',
];

export const ETIQUETA_ENLACE: Record<TipoEnlaceBanner, string> = {
  ninguno: 'Sin enlace',
  producto: 'Prenda',
  categoria: 'Categoría',
  oferta: 'Oferta',
  url: 'Enlace externo',
};
