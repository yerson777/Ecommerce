export interface MiPedidoItem {
  id: number;
  producto_id: number;
  producto_codigo?: string | null;
  producto_nombre?: string | null;
  producto_talla?: string | null;
  producto_color?: string | null;
  producto_imagen?: string | null;
  precio_unitario: string;
  precio_original?: string | null;
  descuento?: string | number;
  oferta?: string | null;
}

export interface MiPedido {
  numero_pedido: string;
  estado: string;
  estado_pago: string;
  fecha_pedido: string | null;
  total: string;
  total_pagado: string;
  saldo: string;
  items_count?: number;
  items: MiPedidoItem[];
}

export interface MiPedidoEnvio {
  estado?: string | null;
  paqueteria?: string | null;
  numero_guia?: string | null;
  enviado_en?: string | null;
  entregado_en?: string | null;
}

export interface MiPedidoDetalle extends MiPedido {
  subtotal: string;
  descuento: string;
  costo_envio: string;
  cupon?: string | null;
  comprobante_url?: string | null;
  metodo_pago?: string | null;
  metodo_entrega?: string | null;
  zona_envio?: string | null;
  envio?: MiPedidoEnvio | null;
  cliente?: {
    nombre?: string | null;
    telefono?: string | null;
    email?: string | null;
    ciudad?: string | null;
    direccion?: string | null;
  } | null;
}

export interface MiPedidosPagina {
  items: MiPedido[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
