import type { Producto } from './producto';
import type { Venta } from './venta';

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
    total_pendiente: string;
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
}