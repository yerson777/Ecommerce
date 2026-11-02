import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import {
  CambioEstadoEnvioPayload,
  CostoRealPayload,
  DatosEnvioPayload,
  EnvioFiltros,
  ModalidadEnvio,
  ModalidadEntrega,
  ZonaEnvio,
  ZonaEnvioPayload,
} from '../models/envio';
import { Paginated } from '../models/paginated';
import { MetodoPagoRef } from '../models/pago';
import { Pedido } from '../models/pedido';
import { toQueryString } from '../utils/query';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class EnviosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: EnvioFiltros): Observable<ApiResponse<Paginated<Pedido>>> {
    return this.get<ApiResponse<Paginated<Pedido>>>(
      `/v1/admin/envios${toQueryString({ ...filtros })}`,
    );
  }

  porEnviar(filtros: EnvioFiltros): Observable<ApiResponse<Paginated<Pedido>>> {
    return this.get<ApiResponse<Paginated<Pedido>>>(
      `/v1/admin/envios/por-enviar${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Pedido>> {
    return this.get<ApiResponse<Pedido>>(`/v1/admin/envios/${id}`);
  }

  cambiarEstado(
    id: number,
    payload: CambioEstadoEnvioPayload,
  ): Observable<ApiResponse<Pedido>> {
    return this.post<ApiResponse<Pedido>>(`/v1/admin/envios/${id}/estado`, payload);
  }

  actualizarDatos(id: number, payload: DatosEnvioPayload): Observable<ApiResponse<Pedido>> {
    return this.put<ApiResponse<Pedido>>(`/v1/admin/envios/${id}/datos`, payload);
  }

  registrarCostoReal(id: number, payload: CostoRealPayload): Observable<ApiResponse<Pedido>> {
    return this.post<ApiResponse<Pedido>>(`/v1/admin/envios/${id}/costo-real`, payload);
  }

  exportar(filtros: EnvioFiltros): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/v1/admin/envios/exportar${toQueryString({ ...filtros })}`, {
      responseType: 'blob',
    });
  }

  modalidadesConfig(): Observable<ApiResponse<ModalidadEntrega[]>> {
    return this.get<ApiResponse<ModalidadEntrega[]>>('/v1/admin/envios/config/modalidades');
  }

  actualizarModalidad(
    id: number,
    payload: { modalidad: ModalidadEnvio; activo?: boolean; costo?: number },
  ): Observable<ApiResponse<ModalidadEntrega>> {
    return this.put<ApiResponse<ModalidadEntrega>>(
      `/v1/admin/envios/config/modalidades/${id}`,
      payload,
    );
  }

  zonas(): Observable<ApiResponse<ZonaEnvio[]>> {
    return this.get<ApiResponse<ZonaEnvio[]>>('/v1/admin/envios/config/zonas');
  }

  crearZona(payload: ZonaEnvioPayload): Observable<ApiResponse<ZonaEnvio>> {
    return this.post<ApiResponse<ZonaEnvio>>('/v1/admin/envios/config/zonas', payload);
  }

  actualizarZona(id: number, payload: ZonaEnvioPayload): Observable<ApiResponse<ZonaEnvio>> {
    return this.put<ApiResponse<ZonaEnvio>>(`/v1/admin/envios/config/zonas/${id}`, payload);
  }

  cambiarEstadoZona(id: number, activo: boolean): Observable<ApiResponse<ZonaEnvio>> {
    return this.post<ApiResponse<ZonaEnvio>>(`/v1/admin/envios/config/zonas/${id}/estado`, {
      activo,
    });
  }

  metodosPago(): Observable<ApiResponse<MetodoPagoRef[]>> {
    return this.get<ApiResponse<MetodoPagoRef[]>>('/v1/admin/metodos-pago');
  }
}
