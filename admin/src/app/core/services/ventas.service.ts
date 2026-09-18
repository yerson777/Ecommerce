import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { Venta, VentaFiltros } from '../models/venta';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class VentasService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: VentaFiltros): Observable<ApiResponse<Paginated<Venta>>> {
    return this.get<ApiResponse<Paginated<Venta>>>(
      `/v1/admin/ventas${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Venta>> {
    return this.get<ApiResponse<Venta>>(`/v1/admin/ventas/${id}`);
  }
}