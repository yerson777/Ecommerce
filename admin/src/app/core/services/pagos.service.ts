import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { MetodoPagoRef, Pago, PagoFiltros, PagoPayload } from '../models/pago';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class PagosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: PagoFiltros): Observable<ApiResponse<Paginated<Pago>>> {
    return this.get<ApiResponse<Paginated<Pago>>>(
      `/v1/admin/pagos${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Pago>> {
    return this.get<ApiResponse<Pago>>(`/v1/admin/pagos/${id}`);
  }

  metodosPago(): Observable<ApiResponse<MetodoPagoRef[]>> {
    return this.get<ApiResponse<MetodoPagoRef[]>>('/v1/admin/metodos-pago');
  }

  registrar(payload: PagoPayload): Observable<ApiResponse<Pago>> {
    const formData = new FormData();
    formData.append('pedido_id', String(payload.pedido_id));
    formData.append('monto', String(payload.monto));
    formData.append('metodo_pago_id', String(payload.metodo_pago_id));
    if (payload.fecha) {
      formData.append('fecha', payload.fecha);
    }
    if (payload.estado) {
      formData.append('estado', payload.estado);
    }
    if (payload.referencia) {
      formData.append('referencia', payload.referencia);
    }
    if (payload.nota) {
      formData.append('nota', payload.nota);
    }
    formData.append('permitir_excedente', payload.permitir_excedente ? '1' : '0');
    if (payload.comprobante) {
      formData.append('comprobante', payload.comprobante);
    }

    return this.postFormData<ApiResponse<Pago>>('/v1/admin/pagos', formData);
  }

  confirmar(id: number): Observable<ApiResponse<Pago>> {
    return this.post<ApiResponse<Pago>>(`/v1/admin/pagos/${id}/confirmar`, {});
  }

  anular(id: number): Observable<ApiResponse<Pago>> {
    return this.post<ApiResponse<Pago>>(`/v1/admin/pagos/${id}/anular`, {});
  }

  adjuntarComprobante(id: number, archivo: File): Observable<ApiResponse<Pago>> {
    const formData = new FormData();
    formData.append('comprobante', archivo);

    return this.postFormData<ApiResponse<Pago>>(`/v1/admin/pagos/${id}/comprobante`, formData);
  }

  descargarComprobante(id: number): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/v1/admin/pagos/${id}/comprobante`, {
      responseType: 'blob',
    });
  }
}