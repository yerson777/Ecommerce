import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { MetodoEntregaConfig, MetodoPagoConfig } from '../models/configuracion';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class ConfiguracionService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  metodosPago(): Observable<ApiResponse<MetodoPagoConfig[]>> {
    return this.get<ApiResponse<MetodoPagoConfig[]>>('/v1/admin/config/metodos-pago');
  }

  actualizarMetodoPago(id: number, datos: { activo: boolean }): Observable<ApiResponse<MetodoPagoConfig>> {
    return this.put<ApiResponse<MetodoPagoConfig>>(`/v1/admin/config/metodos-pago/${id}`, datos);
  }

  metodosEntrega(): Observable<ApiResponse<MetodoEntregaConfig[]>> {
    return this.get<ApiResponse<MetodoEntregaConfig[]>>('/v1/admin/config/metodos-entrega');
  }

  actualizarMetodoEntrega(
    id: number,
    datos: { activo?: boolean; costo?: number },
  ): Observable<ApiResponse<MetodoEntregaConfig>> {
    return this.put<ApiResponse<MetodoEntregaConfig>>(`/v1/admin/config/metodos-entrega/${id}`, datos);
  }
}