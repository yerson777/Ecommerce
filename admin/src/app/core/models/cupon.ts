export type TipoCupon = 'porcentaje' | 'fijo';

export type EstadoCupon = 'activo' | 'inactivo' | 'vencido' | 'agotado';

export interface CategoriaRefCupon {
  id: number;
  nombre: string;
}

export interface CuponUso {
  id: number;
  pedido_id: number | null;
  numero_pedido: string | null;
  cliente: string | null;
  telefono: string | null;
  email: string | null;
  monto_descontado: string;
  fecha: string;
}

export interface Cupon {
  id: number;
  codigo: string;
  tipo: TipoCupon;
  valor: string;
  minimo_compra: string | null;
  limite_usos: number | null;
  limite_por_cliente: number | null;
  usos: number;
  activo: boolean;
  estado: EstadoCupon;
  fecha_inicio: string | null;
  vence_en: string | null;
  pedidos_count: number;
  categorias?: CategoriaRefCupon[];
  categorias_ids?: number[];
  total_descontado?: string;
  usos_registrados?: CuponUso[];
  created_at: string;
}

export interface CuponDatos {
  codigo: string;
  tipo: TipoCupon;
  valor: number;
  minimo_compra?: number | null;
  limite_usos?: number | null;
  limite_por_cliente?: number | null;
  activo?: boolean;
  fecha_inicio?: string | null;
  vence_en?: string | null;
  categorias?: number[];
}

export const TIPO_ETIQUETAS: Record<TipoCupon, string> = {
  porcentaje: 'Porcentaje',
  fijo: 'Monto fijo',
};

export const TIPOS_CUPON: TipoCupon[] = ['porcentaje', 'fijo'];

export const ESTADO_ETIQUETAS: Record<EstadoCupon, string> = {
  activo: 'Activo',
  inactivo: 'Desactivado',
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
