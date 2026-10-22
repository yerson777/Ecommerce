import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { MetodoPagoRef } from '../models/pago';
import { Venta } from '../models/venta';
import {
  Devolucion,
  DevolucionFiltros,
  DevolucionPayload,
  DevolucionPrenda,
  VentaDisponible,
} from '../models/devolucion';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class DevolucionesService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: DevolucionFiltros): Observable<ApiResponse<Paginated<Devolucion>>> {
    return this.get<ApiResponse<Paginated<Devolucion>>>(
      `/v1/admin/devoluciones${toQueryString({ ...filtros })}`,
    );
  }

  ventasDisponibles(busqueda?: string): Observable<ApiResponse<{ data: VentaDisponible[] }>> {
    return this.get<ApiResponse<{ data: VentaDisponible[] }>>(
      `/v1/admin/devoluciones/ventas-disponibles${toQueryString(busqueda ? { busqueda } : {})}`,
    );
  }

  prendasDeVenta(ventaId: number): Observable<ApiResponse<DevolucionPrenda[]>> {
    return this.get<ApiResponse<Venta>>(`/v1/admin/ventas/${ventaId}`).pipe(
      map(
        (res): ApiResponse<DevolucionPrenda[]> => ({
          ...res,
          data: (res.data?.items ?? []).map((item) => ({
            producto_id: item.producto_id,
            codigo: item.producto_codigo ?? null,
            nombre: item.producto_nombre ?? null,
            talla: null,
            precio_unitario: item.precio_unitario,
          })),
        }),
      ),
    );
  }

  metodosPago(): Observable<ApiResponse<{ id: number; nombre: string }[]>> {
    return this.get<ApiResponse<{ id: number; nombre: string }[]>>('/v1/admin/metodos-pago');
  }

  crear(datos: DevolucionPayload): Observable<ApiResponse<Devolucion>> {
    return this.post<ApiResponse<Devolucion>>('/v1/admin/devoluciones', {
      venta_id: datos.venta_id,
      motivo: datos.motivo,
      monto_reembolso: datos.monto_reembolso ?? null,
      tipo: datos.tipo ?? 'total',
      motivo_categoria: datos.motivo_categoria ?? null,
      nota: datos.nota ?? null,
      reembolso_metodo_id: datos.reembolso_metodo_id ?? null,
      items: datos.items ?? null,
    });
  }

  aprobar(id: number): Observable<ApiResponse<Devolucion>> {
    return this.post<ApiResponse<Devolucion>>(`/v1/admin/devoluciones/${id}/aprobar`, {});
  }

  rechazar(id: number, motivo?: string | null): Observable<ApiResponse<Devolucion>> {
    return this.post<ApiResponse<Devolucion>>(`/v1/admin/devoluciones/${id}/rechazar`, {
      motivo: motivo ?? null,
    });
  }

  reembolsar(id: number, reembolsoMetodoId?: number | null): Observable<ApiResponse<Devolucion>> {
    return this.post<ApiResponse<Devolucion>>(`/v1/admin/devoluciones/${id}/reembolsar`, {
      reembolso_metodo_id: reembolsoMetodoId ?? null,
    });
  }
}
