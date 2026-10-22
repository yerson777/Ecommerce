import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Compra, CompraDatos, CompraFiltros } from '../models/compra';
import { Paginated } from '../models/paginated';
import { toQueryString } from '../utils/query';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class ComprasService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: CompraFiltros = {}): Observable<ApiResponse<Paginated<Compra>>> {
    return this.get<ApiResponse<Paginated<Compra>>>(
      `/v1/admin/compras${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Compra>> {
    return this.get<ApiResponse<Compra>>(`/v1/admin/compras/${id}`);
  }

  crear(datos: CompraDatos): Observable<ApiResponse<Compra>> {
    return this.post<ApiResponse<Compra>>('/v1/admin/compras', datos);
  }

  actualizar(id: number, datos: CompraDatos): Observable<ApiResponse<Compra>> {
    return this.put<ApiResponse<Compra>>(`/v1/admin/compras/${id}`, datos);
  }

  anular(id: number): Observable<ApiResponse<Compra>> {
    return this.post<ApiResponse<Compra>>(`/v1/admin/compras/${id}/anular`, {});
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/compras/${id}`);
  }

  exportar(filtros: CompraFiltros = {}): Observable<Blob> {
    return this.http.get(
      `${this.baseUrl}/v1/admin/compras/exportar${toQueryString({ ...filtros })}`,
      { responseType: 'blob' },
    );
  }
}
