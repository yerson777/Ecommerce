import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Oferta, OfertaDatos } from '../models/oferta';
import { ApiService } from './api.service';

export interface OfertaFiltros {
  busqueda?: string;
  estado?: string;
}

@Injectable({ providedIn: 'root' })
export class OfertasService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: OfertaFiltros = {}): Observable<ApiResponse<Oferta[]>> {
    const query = new URLSearchParams();
    if (filtros.busqueda) {
      query.set('busqueda', filtros.busqueda);
    }
    if (filtros.estado) {
      query.set('estado', filtros.estado);
    }
    const qs = query.toString();
    return this.get<ApiResponse<Oferta[]>>(`/v1/admin/ofertas${qs ? `?${qs}` : ''}`);
  }

  detalle(id: number): Observable<ApiResponse<Oferta>> {
    return this.get<ApiResponse<Oferta>>(`/v1/admin/ofertas/${id}`);
  }

  crear(datos: OfertaDatos): Observable<ApiResponse<Oferta>> {
    return this.post<ApiResponse<Oferta>>('/v1/admin/ofertas', datos);
  }

  actualizar(id: number, datos: OfertaDatos): Observable<ApiResponse<Oferta>> {
    return this.put<ApiResponse<Oferta>>(`/v1/admin/ofertas/${id}`, datos);
  }

  cambiarEstado(id: number, activo: boolean): Observable<ApiResponse<Oferta>> {
    return this.post<ApiResponse<Oferta>>(`/v1/admin/ofertas/${id}/estado`, { activo });
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/ofertas/${id}`);
  }
}
