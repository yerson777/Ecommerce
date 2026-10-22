import type { Compra } from './compra';

export type TipoProveedor = 'fardo_coreano' | 'fardo_americano' | 'otro';

export interface Proveedor {
  id: number;
  nombre: string;
  contacto: string | null;
  telefono: string | null;
  ciudad: string | null;
  tipo: TipoProveedor;
  nota: string | null;
  activo: boolean;
  compras_count?: number;
  total_comprado?: string;
  prendas_count?: number;
  historial?: Compra[];
  created_at?: string;
  updated_at?: string;
}

export interface ProveedorDatos {
  nombre: string;
  contacto?: string | null;
  telefono?: string | null;
  ciudad?: string | null;
  tipo: TipoProveedor;
  nota?: string | null;
  activo?: boolean;
}

export interface ProveedorFiltros {
  busqueda?: string;
  tipo?: TipoProveedor | '';
  activo?: boolean | null;
}

export const TIPOS_PROVEEDOR: TipoProveedor[] = [
  'fardo_coreano',
  'fardo_americano',
  'otro',
];

export const TIPO_PROVEEDOR_ETIQUETAS: Record<TipoProveedor, string> = {
  fardo_coreano: 'Fardo coreano',
  fardo_americano: 'Fardo americano',
  otro: 'Otro',
};
