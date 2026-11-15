import { MEDIDA_BANNER_INICIO } from '../../../../../shared/banner-medida-inicio';
import type { MedidaBanner } from '../../../../../shared/banner-medida-inicio';

export type UbicacionBanner = 'inicio' | 'catalogo';

export type { MedidaBanner };

export const MEDIDAS_BANNER: Record<UbicacionBanner, MedidaBanner> = {
  inicio: MEDIDA_BANNER_INICIO,
  catalogo: { ancho: 2100, alto: 900, aspecto: '21 / 9', proporcion: '21:9' },
};

export interface BannerPublico {
  id: number;
  url: string;
  url_movil: string | null;
  titulo: string | null;
  subtitulo: string | null;
  enlace: string | null;
  orden: number;
}
