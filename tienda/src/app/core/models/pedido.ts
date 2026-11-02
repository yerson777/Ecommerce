export type ModalidadEnvio = 'recojo' | 'ciudad' | 'paqueteria';

export interface MetodoEntrega {
  id: number;
  nombre: string;
  costo: string;
  modalidad?: ModalidadEnvio;
}

export interface ZonaEnvioPublica {
  id: number;
  nombre: string;
  departamento: string | null;
  costo: string;
  tiempo_estimado: string | null;
  envio_gratis_desde: string | null;
  por_confirmar: boolean;
}

export interface CotizacionEnvio {
  metodo_entrega_id: number;
  modalidad: ModalidadEnvio;
  zona_envio_id: number | null;
  zona: string | null;
  costo: string;
  es_gratis: boolean;
  por_confirmar: boolean;
  tiempo_estimado: string | null;
}

export interface MetodoPago {
  id: number;
  nombre: string;
  codigo?: string | null;
  instrucciones?: string | null;
  titular?: string | null;
  banco?: string | null;
  numero_cuenta?: string | null;
  qr_url?: string | null;
  orden?: number;
}

export type EstadoPedido = 'pendiente' | 'confirmado' | 'cancelado' | 'completado';

export interface PedidoPublicoCliente {
  nombre: string;
  telefono: string;
  email: string | null;
  ciudad: string | null;
  direccion: string | null;
}

export interface PedidoPublicoItem {
  id: number;
  producto_id: number;
  producto_codigo: string;
  producto_nombre: string;
  producto_talla: string | null;
  producto_color: string | null;
  producto_imagen: string | null;
  precio_unitario: string;
  precio_original?: string;
  descuento?: string | number;
  oferta?: string | null;
}

export interface PedidoPublico {
  numero_pedido: string;
  estado: EstadoPedido;
  subtotal: string;
  descuento?: string | number;
  costo_envio: string;
  total: string;
  fecha_pedido: string;
  created_at: string | null;
  metodo_pago: string | null;
  metodo_entrega: string | null;
  comprobante_url: string | null;
  cliente: PedidoPublicoCliente | null;
  items: PedidoPublicoItem[];
}

export interface PrecioEsperado {
  producto_id: number;
  precio: number;
}

export interface CheckoutPayload {
  productos: number[];
  nombre: string;
  telefono: string;
  email: string | null;
  ciudad: string;
  direccion: string;
  notas: string | null;
  metodo_pago_id: number | null;
  metodo_entrega_id: number | null;
  zona_envio_id?: number | null;
  codigo_cupon?: string | null;
  precios_esperados?: PrecioEsperado[];
}