import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import {
  Reserva,
  ReservaFiltros,
  ReservaPayload,
  ReservaVentaPayload,
} from '../models/reserva';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class ReservasService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: ReservaFiltros): Observable<ApiResponse<Paginated<Reserva>>> {
    return this.get<ApiResponse<Paginated<Reserva>>>(
      `/v1/admin/reservas${toQueryString({ ...filtros })}`,
    );
  }

  crear(datos: ReservaPayload): Observable<ApiResponse<Reserva>> {
    return this.post<ApiResponse<Reserva>>('/v1/admin/reservas', {
      producto_id: datos.producto_id,
      cliente_id: datos.cliente_id,
      vence_en: datos.vence_en ?? null,
      pedido_id: datos.pedido_id ?? null,
    });
  }

  convertirEnVenta(id: number, datos: ReservaVentaPayload): Observable<ApiResponse<Reserva>> {
    return this.post<ApiResponse<Reserva>>(`/v1/admin/reservas/${id}/convertir`, {
      costo_envio: datos.costo_envio ?? 0,
      notas: datos.notas ?? null,
    });
  }

  liberar(id: number): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>(`/v1/admin/reservas/${id}/liberar`, {});
  }
}