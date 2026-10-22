export type EstadoDevolucion = 'pendiente' | 'aprobada' | 'reembolsada' | 'rechazada';

export type TipoDevolucion = 'total' | 'parcial';

export interface DevolucionPrecioPedido {
  id: number;
  numero_pedido: string;
  estado: string;
  total: string;
  fecha_pedido: string;
  cliente?: { id: number; nombre: string; telefono: string | null } | null;
}

export interface DevolucionPrenda {
  producto_id: number;
  codigo?: string | null;
  nombre?: string | null;
  talla?: string | null;
  precio_unitario: string;
  reingresa?: boolean;
}

export interface Devolucion {
  id: number;
  pedido_id: number;
  estado: EstadoDevolucion;
  tipo: TipoDevolucion;
  monto_reembolsado: string;
  reembolso_metodo_id?: number | null;
  reembolso_metodo?: string | null;
  motivo: string;
  motivo_categoria?: string | null;
  nota?: string | null;
  rechazado_motivo?: string | null;
  devuelto_en: string | null;
  revisado_en?: string | null;
  reembolsado_en?: string | null;
  created_at: string;
  pedido?: DevolucionPrecioPedido | null;
  venta?: { id: number; numero_venta: string; total: string } | null;
  prendas?: DevolucionPrenda[];
}

export interface VentaDisponible {
  id: number;
  numero_venta: string;
  fecha_venta: string | null;
  total: string;
  pedido_id: number | null;
  cliente_id: number | null;
  cliente?: { id: number; nombre: string; telefono: string | null } | null;
}

export interface DevolucionItemPayload {
  producto_id: number;
  reingresa: boolean;
}

export interface DevolucionFiltros {
  busqueda?: string;
  estado?: EstadoDevolucion | '';
  tipo?: TipoDevolucion | '';
  fecha_desde?: string;
  fecha_hasta?: string;
  page?: number;
  per_page?: number;
}

export interface DevolucionPayload {
  venta_id: number;
  motivo: string;
  monto_reembolso?: number | null;
  tipo?: TipoDevolucion;
  motivo_categoria?: string | null;
  nota?: string | null;
  reembolso_metodo_id?: number | null;
  items?: DevolucionItemPayload[] | null;
}

export interface MotivoDevolucionOpcion {
  valor: string;
  etiqueta: string;
}

export const MOTIVOS_DEVOLUCION: MotivoDevolucionOpcion[] = [
  { valor: 'talla', etiqueta: 'Talla incorrecta' },
  { valor: 'defecto', etiqueta: 'Defecto de fábrica' },
  { valor: 'no_gusto', etiqueta: 'No era lo esperado' },
  { valor: 'danada', etiqueta: 'Llegó dañada' },
  { valor: 'otro', etiqueta: 'Otro' },
];

export const ETIQUETA_ESTADO_DEVOLUCION: Record<EstadoDevolucion, string> = {
  pendiente: 'Pendiente',
  aprobada: 'Aprobada',
  reembolsada: 'Reembolsada',
  rechazada: 'Rechazada',
};

export const TONO_ESTADO_DEVOLUCION: Record<EstadoDevolucion, string> = {
  pendiente: 'warning',
  aprobada: 'info',
  reembolsada: 'success',
  rechazada: 'danger',
};
