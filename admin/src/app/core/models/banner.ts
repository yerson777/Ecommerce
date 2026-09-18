export interface Banner {
  id: number;
  ruta: string;
  url: string;
  titulo: string | null;
  subtitulo: string | null;
  enlace: string | null;
  activo: boolean;
  orden: number;
  created_at?: string;
  updated_at?: string;
}

export interface BannerPayload {
  titulo?: string | null;
  subtitulo?: string | null;
  enlace?: string | null;
  activo?: boolean;
  orden?: number;
}