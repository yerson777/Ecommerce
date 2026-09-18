import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../models/api-response';
import {
  ClientesReporte,
  InventarioReporte,
  MetodoPagoRef,
  PagosPorMetodo,
  PagosReporte,
  PedidosReporte,
  ProductoMasVendido,
  ReporteFiltros,
  ReporteResumen,
  VentasPorCategoria,
  VentasPorPeriodoResultado,
  VentasPorTalla,
} from '../models/reporte';

@Injectable({ providedIn: 'root' })
export class ReportesService {
  private readonly baseUrl = environment.apiUrl;

  constructor(private readonly http: HttpClient) {}

  private params(filtros: object = {}): HttpParams {
    let params = new HttpParams();

    for (const [key, value] of Object.entries(filtros as Record<string, unknown>)) {
      if (value === null || value === undefined || value === '') {
        continue;
      }
      params = params.set(key, String(value));
    }

    return params;
  }

  resumen(filtros: ReporteFiltros = {}): Observable<ApiResponse<ReporteResumen>> {
    return this.http.get<ApiResponse<ReporteResumen>>(`${this.baseUrl}/v1/admin/reportes/dashboard`, { params: this.params(filtros) });
  }

  ventasPorPeriodo(filtros: ReporteFiltros = {}): Observable<ApiResponse<VentasPorPeriodoResultado>> {
    return this.http.get<ApiResponse<VentasPorPeriodoResultado>>(`${this.baseUrl}/v1/admin/reportes/ventas-periodo`, { params: this.params(filtros) });
  }

  productosMasVendidos(filtros: ReporteFiltros = {}): Observable<ApiResponse<ProductoMasVendido[]>> {
    return this.http.get<ApiResponse<ProductoMasVendido[]>>(`${this.baseUrl}/v1/admin/reportes/productos-mas-vendidos`, { params: this.params(filtros) });
  }

  ventasPorCategoria(filtros: ReporteFiltros = {}): Observable<ApiResponse<VentasPorCategoria[]>> {
    return this.http.get<ApiResponse<VentasPorCategoria[]>>(`${this.baseUrl}/v1/admin/reportes/ventas-por-categoria`, { params: this.params(filtros) });
  }

  ventasPorTalla(filtros: ReporteFiltros = {}): Observable<ApiResponse<VentasPorTalla[]>> {
    return this.http.get<ApiResponse<VentasPorTalla[]>>(`${this.baseUrl}/v1/admin/reportes/ventas-por-talla`, { params: this.params(filtros) });
  }

  clientes(filtros: ReporteFiltros = {}): Observable<ApiResponse<ClientesReporte>> {
    return this.http.get<ApiResponse<ClientesReporte>>(`${this.baseUrl}/v1/admin/reportes/clientes`, { params: this.params(filtros) });
  }

  pagos(filtros: ReporteFiltros & { metodo_pago_id?: number } = {}): Observable<ApiResponse<PagosReporte>> {
    return this.http.get<ApiResponse<PagosReporte>>(`${this.baseUrl}/v1/admin/reportes/pagos`, { params: this.params(filtros) });
  }

  pagosPorMetodo(filtros: ReporteFiltros = {}): Observable<ApiResponse<PagosPorMetodo[]>> {
    return this.http.get<ApiResponse<PagosPorMetodo[]>>(`${this.baseUrl}/v1/admin/reportes/pagos-por-metodo`, { params: this.params(filtros) });
  }

  inventario(filtros: { categoria_id?: number; talla_id?: number; estado?: string } = {}): Observable<ApiResponse<InventarioReporte>> {
    return this.http.get<ApiResponse<InventarioReporte>>(`${this.baseUrl}/v1/admin/reportes/inventario`, { params: this.params(filtros) });
  }

  pedidos(filtros: Record<string, unknown> = {}): Observable<ApiResponse<PedidosReporte>> {
    return this.http.get<ApiResponse<PedidosReporte>>(`${this.baseUrl}/v1/admin/reportes/pedidos`, { params: this.params(filtros) });
  }

  metodosPago(): Observable<ApiResponse<MetodoPagoRef[]>> {
    return this.http.get<ApiResponse<MetodoPagoRef[]>>(`${this.baseUrl}/v1/admin/reportes/metodos-pago`);
  }

  exportar(tipo: 'ventas' | 'pedidos' | 'pagos' | 'inventario', filtros: Record<string, unknown> = {}): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/v1/admin/reportes/exportar/${tipo}`, {
      params: this.params(filtros),
      responseType: 'blob',
    });
  }
}