import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { ApiService } from './api.service';

export interface Notificacion {
  id: number;
  pedido_id: number | null;
  cliente_id: number | null;
  pago_id: number | null;
  tipo: string;
  mensaje: string;
  leida: boolean;
  leida_en: string | null;
  creada_en: string | null;
}

export interface ListarNotificacionesResponse extends ApiResponse<Notificacion[]> {
  meta?: {
    total: number;
    no_leidas: number;
  };
}

@Injectable({ providedIn: 'root' })
export class NotificacionesService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(params: { solo_no_leidas?: boolean; por_pagina?: number } = {}): Observable<ListarNotificacionesResponse> {
    const query = new URLSearchParams();

    if (params.solo_no_leidas) {
      query.set('solo_no_leidas', '1');
    }

    query.set('por_pagina', String(params.por_pagina ?? 20));

    return this.get<ListarNotificacionesResponse>(`/v1/admin/notificaciones?${query.toString()}`);
  }

  contador(): Observable<ApiResponse<{ total: number; no_leidas: number }>> {
    return this.get<ApiResponse<{ total: number; no_leidas: number }>>('/v1/admin/notificaciones/contador');
  }

  marcarLeida(id: number): Observable<ApiResponse<Notificacion>> {
    return this.post<ApiResponse<Notificacion>>(`/v1/admin/notificaciones/${id}/leida`, {});
  }

  marcarTodasLeidas(): Observable<ApiResponse<{ marcadas: number }>> {
    return this.post<ApiResponse<{ marcadas: number }>>('/v1/admin/notificaciones/leidas', {});
  }
}