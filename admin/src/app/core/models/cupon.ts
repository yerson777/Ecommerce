export type TipoCupon = 'porcentaje' | 'fijo';

export type EstadoCupon = 'activo' | 'inactivo' | 'vencido' | 'agotado';

export interface Cupon {
  id: number;
  codigo: string;
  tipo: TipoCupon;
  valor: string;
  minimo_compra: string | null;
  limite_usos: number | null;
  usos: number;
  activo: boolean;
  estado: EstadoCupon;
  vence_en: string | null;
  pedidos_count: number;
  created_at: string;
}

export interface CuponDatos {
  codigo: string;
  tipo: TipoCupon;
  valor: number;
  minimo_compra?: number | null;
  limite_usos?: number | null;
  activo?: boolean;
  vence_en?: string | null;
}

export const TIPO_ETIQUETAS: Record<TipoCupon, string> = {
  porcentaje: 'Porcentaje',
  fijo: 'Monto fijo',
};

export const TIPOS_CUPON: TipoCupon[] = ['porcentaje', 'fijo'];

export const ESTADO_ETIQUETAS: Record<EstadoCupon, string> = {
  activo: 'Activo',
  inactivo: 'Inactivo',
  vencido: 'Vencido',
  agotado: 'Agotado',
};

export const TONO_ESTADO: Record<EstadoCupon, 'success' | 'neutral' | 'warning' | 'danger'> = {
  activo: 'success',
  inactivo: 'neutral',
  vencido: 'warning',
  agotado: 'danger',
};

export const TONO_TIPO: Record<TipoCupon, 'primary' | 'info'> = {
  porcentaje: 'primary',
  fijo: 'info',
};
