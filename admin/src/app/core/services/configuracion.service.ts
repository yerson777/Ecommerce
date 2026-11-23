import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, shareReplay } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import {
  ConfiguracionHistorial,
  ConfiguracionTienda,
  MetodoEntregaConfig,
  MetodoPagoConfig,
} from '../models/configuracion';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class ConfiguracionService extends ApiService {
  private logoPublico$: Observable<ApiResponse<ConfiguracionTienda>> | null = null;

  constructor(http: HttpClient) {
    super(http);
  }

  metodosPago(): Observable<ApiResponse<MetodoPagoConfig[]>> {
    return this.get<ApiResponse<MetodoPagoConfig[]>>('/v1/admin/config/metodos-pago');
  }

  actualizarMetodoPago(id: number, datos: { activo: boolean }): Observable<ApiResponse<MetodoPagoConfig>> {
    return this.put<ApiResponse<MetodoPagoConfig>>(`/v1/admin/config/metodos-pago/${id}`, datos);
  }

  guardarMetodoPago(id: number, datos: FormData): Observable<ApiResponse<MetodoPagoConfig>> {
    return this.postFormData<ApiResponse<MetodoPagoConfig>>(`/v1/admin/config/metodos-pago/${id}`, datos);
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

  datosTienda(): Observable<ApiResponse<ConfiguracionTienda>> {
    return this.get<ApiResponse<ConfiguracionTienda>>('/v1/admin/config/tienda');
  }

  /** Endpoint público (sin sesión) para obtener logo_url en el login. */
  logoTienda(): Observable<ApiResponse<ConfiguracionTienda>> {
    if (!this.logoPublico$) {
      this.logoPublico$ = this.get<ApiResponse<ConfiguracionTienda>>(
        '/v1/store/configuracion',
      ).pipe(shareReplay(1));
    }
    return this.logoPublico$;
  }

  actualizarTienda(datos: FormData): Observable<ApiResponse<ConfiguracionTienda>> {
    return this.postFormData<ApiResponse<ConfiguracionTienda>>('/v1/admin/config/tienda', datos);
  }

  historialTienda(): Observable<ApiResponse<ConfiguracionHistorial[]>> {
    return this.get<ApiResponse<ConfiguracionHistorial[]>>('/v1/admin/config/tienda/historial');
  }
}