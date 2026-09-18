import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { InventarioResumen } from '../models/dashboard';
import { ApiService } from './api.service';

export interface VenderPayload {
  producto_id: number;
  cliente_id: number;
  costo_envio: number;
  notas?: string | null;
}

@Injectable({ providedIn: 'root' })
export class InventarioService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  resumen(): Observable<ApiResponse<InventarioResumen>> {
    return this.get<ApiResponse<InventarioResumen>>('/v1/admin/inventario');
  }

  reservar(productoId: number, venceEn?: string): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>('/v1/admin/inventario/reservar', {
      producto_id: productoId,
      vence_en: venceEn ?? null,
    });
  }

  liberar(productoId: number): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>('/v1/admin/inventario/liberar', {
      producto_id: productoId,
    });
  }

  vender(data: VenderPayload): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>('/v1/admin/inventario/vender', data);
  }
}