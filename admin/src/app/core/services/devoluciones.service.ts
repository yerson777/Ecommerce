import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import {
  Devolucion,
  DevolucionFiltros,
  DevolucionPayload,
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

  crear(datos: DevolucionPayload): Observable<ApiResponse<Devolucion>> {
    return this.post<ApiResponse<Devolucion>>('/v1/admin/devoluciones', {
      venta_id: datos.venta_id,
      motivo: datos.motivo,
      monto_reembolso: datos.monto_reembolso ?? null,
    });
  }

  aprobar(id: number): Observable<ApiResponse<Devolucion>> {
    return this.post<ApiResponse<Devolucion>>(`/v1/admin/devoluciones/${id}/aprobar`, {});
  }
}