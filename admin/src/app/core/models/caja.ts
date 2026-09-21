export type TipoMovimiento = 'ingreso' | 'egreso';
export type FuenteMovimiento = 'pago' | 'gasto' | 'ajuste';

export interface Movimiento {
  id: number;
  tipo: TipoMovimiento;
  monto: string;
  fuente: FuenteMovimiento;
  pago_id: number | null;
  gasto_id: number | null;
  descripcion: string | null;
  fecha: string | null;
  created_at: string | null;
  pago?: {
    numero_pago: string;
    numero_pedido: string | null;
    cliente: string | null;
  } | null;
  gasto?: {
    concepto: string;
    categoria: string | null;
  } | null;
}

export interface SaldoCaja {
  ingresos: string;
  egresos: string;
  saldo: string;
}

export interface DiaFlujo {
  fecha: string;
  ingresos: string;
  egresos: string;
  saldo: string;
}

export interface FlujoCaja {
  inicio: string;
  fin: string;
  dias: DiaFlujo[];
}

export type PeriodoFlujo = 'ultimos_7_dias' | 'ultimos_15_dias' | 'ultimos_30_dias' | 'ultimos_90_dias' | 'este_mes';

export interface CategoriaGastoRef {
  id: number;
  nombre: string;
}

export interface Gasto {
  id: number;
  concepto: string;
  monto: string;
  fecha_gasto: string | null;
  observacion: string | null;
  categoria_gasto: string | null;
  metodo_pago: string | null;
  created_at: string | null;
}

export interface MovimientoFiltros {
  busqueda?: string;
  tipo?: TipoMovimiento | null;
  fecha_desde?: string | null;
  fecha_hasta?: string | null;
  page?: number;
  per_page?: number;
}

export interface GastoPayload {
  concepto: string;
  monto: number;
  categoria_gasto_id: number;
  metodo_pago_id: number;
  fecha_gasto: string;
  observacion?: string | null;
}

export interface IngresoManualPayload {
  tipo: 'ingreso';
  monto: number;
  descripcion: string;
  fecha: string;
}

export interface ObjetivoEliminar {
  id: number;
  origen: 'gasto' | 'movimiento';
  descripcion: string | null;
  monto: string;
}