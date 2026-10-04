import type { Producto } from './producto';
import type { Venta } from './venta';
import type { VentasPorCategoria, VentasPorTalla } from './reporte';

export interface Conteo {
  nombre: string;
  total: number;
}

export type EstadoConteo = Conteo & { estado: string };

export interface InventarioResumen {
  total: number;
  por_estado: EstadoConteo[];
  publicados: number;
  no_publicados: number;
  por_categoria: Conteo[];
  por_talla: Conteo[];
  por_rango_precio: Conteo[];
}

/** Pedido que aún requiere acción: ni completado ni cancelado. */
export interface PedidoPorAtender {
  id: number;
  numero_pedido: string;
  estado: string;
  estado_pago: string;
  cliente: string;
  total: string;
  pagado: string;
  saldo: string;
  fecha_pedido: string;
  reservas: number;
}

/** Prenda única disponible que lleva más de 30 días sin venderse. */
export interface PrendaSinVenta {
  id: number;
  codigo: string;
  nombre: string;
  categoria: string;
  talla: string;
  precio: string;
  publicado: boolean;
  fecha_ingreso: string | null;
  dias_disponible: number;
}

/** Reserva activa ordenada por vencimiento más próximo. */
export interface ReservaPorVencer {
  id: number;
  producto: string;
  categoria: string;
  cliente: string;
  precio: string;
  vence_en: string | null;
  dias_restantes: number;
  vencida: boolean;
}

export interface GananciaPrenda {
  id: number;
  codigo: string;
  nombre: string;
  categoria: string;
  fecha_venta: string;
  precio_venta: string;
  costo: string;
  ganancia: string;
}

/**
 * Ganancia = precio de venta - costo del producto.
 * El costo es el valor actual de productos.costo (no congelado al vender).
 */
export interface GananciaResumen {
  total: string;
  ingresos: string;
  costos: string;
  margen_pct: string;
  prendas: GananciaPrenda[];
}

export interface MetricasDashboard {
  ticket_promedio: string;
  porcentaje_inventario_vendido: string;
  tiempo_promedio_venta_dias: string;
  prendas_vendidas: number;
  prendas_totales: number;
}

export interface DashboardResumen {
  productos: {
    total: number;
    disponibles: number;
    reservadas: number;
    vendidas: number;
    publicados: number;
    no_publicados: number;
    recientes: Producto[];
  };
  ventas: {
    total: number;
    monto: string;
    recientes: Venta[];
  };
  pedidos: number;
  clientes: {
    total: number;
    con_pedidos: number;
    nuevos: number;
    recurrentes: number;
    pedidos_por_cliente: number;
  };
  pagos: {
    total_vendido: string;
    total_cobrado: string;
    /** Suma de los saldos positivos de cada pedido no cancelado (deuda real). */
    total_pendiente: string;
    /** Pagos que exceden el total del pedido. */
    total_excedente: string;
    /** Suma de los totales de los pedidos pendientes + confirmados. */
    pedidos_abiertos: string;
    pagos: {
      total: number;
      completados: number;
      sin_venta: number;
    };
    pedidos: {
      pendientes_de_pago: number;
      parcialmente_pagados: number;
      pagados: number;
    };
  };
  caja: {
    ingresos: string;
    egresos: string;
    saldo: string;
  };
  inventario: {
    por_estado: EstadoConteo[];
    por_categoria: Conteo[];
    por_talla: Conteo[];
  };
  pedidos_por_atender: PedidoPorAtender[];
  prendas_sin_venta: PrendaSinVenta[];
  reservas_por_vencer: ReservaPorVencer[];
  ganancia: GananciaResumen;
  metricas: MetricasDashboard;
  ventas_por_categoria: VentasPorCategoria[];
  ventas_por_talla: VentasPorTalla[];
}
