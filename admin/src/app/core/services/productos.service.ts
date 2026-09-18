import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import {
  Producto,
  ProductoFiltros,
  ProductoHistorialEntry,
  ProductoPayload,
} from '../models/producto';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class ProductosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: ProductoFiltros): Observable<ApiResponse<Paginated<Producto>>> {
    return this.get<ApiResponse<Paginated<Producto>>>(
      `/v1/admin/productos${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Producto>> {
    return this.get<ApiResponse<Producto>>(`/v1/admin/productos/${id}`);
  }

  crear(data: ProductoPayload): Observable<ApiResponse<Producto>> {
    return this.post<ApiResponse<Producto>>('/v1/admin/productos', data);
  }

  actualizar(id: number, data: Partial<ProductoPayload>): Observable<ApiResponse<Producto>> {
    return this.put<ApiResponse<Producto>>(`/v1/admin/productos/${id}`, data);
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/productos/${id}`);
  }

  historial(id: number): Observable<ApiResponse<ProductoHistorialEntry[]>> {
    return this.get<ApiResponse<ProductoHistorialEntry[]>>(`/v1/admin/productos/${id}/historial`);
  }
}