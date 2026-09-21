import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { EstadoPedido, Pedido, PedidoFiltros } from '../models/pedido';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class PedidosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: PedidoFiltros): Observable<ApiResponse<Paginated<Pedido>>> {
    return this.get<ApiResponse<Paginated<Pedido>>>(
      `/v1/admin/pedidos${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Pedido>> {
    return this.get<ApiResponse<Pedido>>(`/v1/admin/pedidos/${id}`);
  }

  cambiarEstado(id: number, estado: EstadoPedido): Observable<ApiResponse<Pedido>> {
    return this.put<ApiResponse<Pedido>>(`/v1/admin/pedidos/${id}/estado`, { estado });
  }

  devolver(id: number, datos: { motivo: string; monto_reembolso?: number | null }): Observable<ApiResponse<Pedido>> {
    return this.post<ApiResponse<Pedido>>(`/v1/admin/pedidos/${id}/devolver`, datos);
  }
}