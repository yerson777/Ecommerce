export type EstadoEnvio = 'por_preparar' | 'preparado' | 'enviado' | 'entregado';

export type ModalidadEnvio = 'recojo' | 'ciudad' | 'paqueteria';

export interface Envio {
  id: number;
  estado: EstadoEnvio;
  transiciones: EstadoEnvio[];
  costo_por_confirmar: boolean;
  preparado_en: string | null;
  enviado_en: string | null;
  entregado_en: string | null;
  paqueteria: string | null;
  numero_guia: string | null;
  nota: string | null;
  costo_real: string | null;
  gasto_id: number | null;
  diferencia: string | null;
}

export interface ZonaEnvioResumen {
  id: number;
  nombre: string;
  departamento: string | null;
  costo: string;
  tiempo_estimado: string | null;
  costo_por_confirmar: boolean;
}

export interface ZonaEnvio {
  id: number;
  nombre: string;
  departamento: string | null;
  costo: string;
  tiempo_estimado: string | null;
  envio_gratis_desde: string | null;
  costo_por_confirmar: boolean;
  activo: boolean;
  orden: number;
}

export interface ModalidadEntrega {
  id: number;
  nombre: string;
  modalidad: ModalidadEnvio;
  activo: boolean;
  costo: string;
  orden?: number;
}

export interface ZonaEnvioPayload {
  nombre: string;
  departamento: string | null;
  costo: number;
  tiempo_estimado: string | null;
  envio_gratis_desde: number | null;
  costo_por_confirmar: boolean;
  activo: boolean;
  orden: number;
}

export interface EnvioFiltros {
  busqueda?: string;
  estado_envio?: EstadoEnvio | '';
  modalidad?: ModalidadEnvio | '';
  fecha_desde?: string;
  fecha_hasta?: string;
  page?: number;
  per_page?: number;
}

export interface CambioEstadoEnvioPayload {
  estado: EstadoEnvio;
  paqueteria?: string | null;
  numero_guia?: string | null;
  nota?: string | null;
}

export interface DatosEnvioPayload {
  paqueteria?: string | null;
  numero_guia?: string | null;
  nota?: string | null;
}

export interface CostoRealPayload {
  costo_real: number;
  metodo_pago_id: number;
}

export const ETIQUETA_ESTADO_ENVIO: Record<EstadoEnvio, string> = {
  por_preparar: 'Por preparar',
  preparado: 'Preparado',
  enviado: 'Enviado',
  entregado: 'Entregado',
};

export const TONO_ESTADO_ENVIO: Record<EstadoEnvio, string> = {
  por_preparar: 'warning',
  preparado: 'info',
  enviado: 'info',
  entregado: 'success',
};

export const ETIQUETA_MODALIDAD: Record<ModalidadEnvio, string> = {
  recojo: 'Retiro en tienda',
  ciudad: 'Envío en ciudad',
  paqueteria: 'Paquetería',
};

export const ESTADOS_ENVIO: EstadoEnvio[] = [
  'por_preparar',
  'preparado',
  'enviado',
  'entregado',
];
