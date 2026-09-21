import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { Cupon, CuponDatos } from '../models/cupon';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

export interface CuponFiltros {
  busqueda?: string;
  tipo?: string;
  estado?: string;
  page?: number;
  per_page?: number;
}

@Injectable({ providedIn: 'root' })
export class CuponesService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: CuponFiltros): Observable<ApiResponse<Paginated<Cupon>>> {
    return this.get<ApiResponse<Paginated<Cupon>>>(
      `/v1/admin/cupones${toQueryString({ ...filtros })}`,
    );
  }

  crear(datos: CuponDatos): Observable<ApiResponse<Cupon>> {
    return this.post<ApiResponse<Cupon>>('/v1/admin/cupones', datos);
  }

  actualizar(id: number, datos: Partial<CuponDatos>): Observable<ApiResponse<Cupon>> {
    return this.put<ApiResponse<Cupon>>(`/v1/admin/cupones/${id}`, datos);
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/cupones/${id}`);
  }
}
