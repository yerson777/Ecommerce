export type PeriodoReporte =
  | 'hoy'
  | 'ayer'
  | 'ultimos_7_dias'
  | 'ultimos_30_dias'
  | 'este_mes'
  | 'mes_anterior'
  | 'este_anio'
  | 'personalizado';

export interface ReporteResumen {
  productos: {
    total: number;
    disponibles: number;
    reservadas: number;
    vendidas: number;
  };
  pedidos: {
    total: number;
    pendientes: number;
    confirmados: number;
    cancelados: number;
    completados: number;
  };
  ventas: {
    total: number;
    monto: string;
    cobrado: string;
    pendiente: string;
  };
  clientes: {
    total: number;
    con_pedidos: number;
    nuevos: number;
    recurrentes: number;
  };
  pagos: {
    total_cobrado: string;
    total_pendiente: string;
    pagados: number;
    parciales: number;
    pendientes: number;
  };
}

export interface VentaPorPeriodo {
  periodo: string;
  cantidad_ventas: number;
  monto_total: string;
}

export interface VentasPorPeriodoResultado {
  agrupacion: string;
  datos: VentaPorPeriodo[];
}

export interface ProductoMasVendido {
  producto_id: number;
  codigo: string;
  nombre: string;
  precio: string;
  categoria: string;
  talla: string;
  cantidad_ventas: number;
  monto_total: string;
  ultima_venta: string | null;
}

export interface VentasPorCategoria {
  categoria: string;
  cantidad_productos: number;
  monto_total: string;
}

export interface VentasPorTalla {
  talla: string;
  cantidad_productos: number;
  monto_total: string;
}

export interface ClienteEstadistica {
  cliente_id: number;
  nombre: string;
  telefono: string | null;
  total_pedidos: number;
  total_comprado: string;
  ultimo_pedido: string | null;
}

export interface ClientesReporte {
  total: number;
  con_pedidos: number;
  sin_compras: number;
  nuevos: number;
  recurrentes: number;
  sin_recompra: number;
  topClientes: ClienteEstadistica[];
}

export interface PagoReporte {
  id: number;
  numero_pago: string;
  monto: string;
  estado: string;
  metodo_pago: string | null;
  pagado_en: string | null;
  pedido_numero: string | null;
  venta_numero: string | null;
  cliente_nombre: string | null;
}

export interface PagosReporte {
  pagos: PagoReporte[];
  resumen: {
    total_cobrado: string;
    total_pendiente: string;
    cantidad_pagos: number;
    pedidos_pagados: number;
    pedidos_parciales: number;
    pedidos_pendientes: number;
  };
}

export interface PagosPorMetodo {
  metodo: string;
  cantidad: number;
  total_monto: string;
}

export interface ConteoNombre {
  nombre: string;
  total: number;
}

export interface ConteoEstado {
  estado: string;
  total: number;
}

export interface InventarioReporte {
  total: number;
  disponibles: number;
  reservadas: number;
  vendidas: number;
  porCategoria: ConteoNombre[];
  porTalla: ConteoNombre[];
  porEstado: ConteoEstado[];
}

export interface PedidoReporte {
  id: number;
  numero_pedido: string;
  cliente: string;
  fecha_pedido: string;
  total: string;
  total_pagado: string;
  saldo_pendiente: string;
  estado: string;
  estado_pago: string;
}

export interface PedidosReporte {
  items: PedidoReporte[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface MetodoPagoRef {
  id: number;
  nombre: string;
}

export interface ReporteFiltros {
  periodo?: PeriodoReporte;
  fecha_desde?: string;
  fecha_hasta?: string;
}