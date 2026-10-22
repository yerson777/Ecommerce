import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import {
  Cliente,
  ClienteDuplicadoRef,
  ClienteFiltros,
  ClientePayload,
  UnirClientesPayload,
} from '../models/cliente';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

interface OpcionCliente {
  id: number;
  nombre: string;
  telefono: string | null;
}

@Injectable({ providedIn: 'root' })
export class ClientesService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: ClienteFiltros): Observable<ApiResponse<Paginated<Cliente>>> {
    return this.get<ApiResponse<Paginated<Cliente>>>(
      `/v1/admin/clientes${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Cliente>> {
    return this.get<ApiResponse<Cliente>>(`/v1/admin/clientes/${id}`);
  }

  crear(payload: ClientePayload): Observable<ApiResponse<Cliente>> {
    return this.post<ApiResponse<Cliente>>('/v1/admin/clientes', payload);
  }

  actualizar(id: number, payload: Partial<ClientePayload>): Observable<ApiResponse<Cliente>> {
    return this.put<ApiResponse<Cliente>>(`/v1/admin/clientes/${id}`, payload);
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/clientes/${id}`);
  }

  duplicados(): Observable<ApiResponse<ClienteDuplicadoRef[][]>> {
    return this.get<ApiResponse<ClienteDuplicadoRef[][]>>('/v1/admin/clientes/duplicados');
  }

  unir(payload: UnirClientesPayload): Observable<ApiResponse<Cliente>> {
    return this.post<ApiResponse<Cliente>>('/v1/admin/clientes/unir', payload);
  }

  exportar(filtros: ClienteFiltros): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/v1/admin/clientes/exportar${toQueryString({ ...filtros })}`, {
      responseType: 'blob',
    });
  }

  opciones(): Observable<ApiResponse<OpcionCliente[]>> {
    return this.get<ApiResponse<OpcionCliente[]>>('/v1/admin/clientes/opciones');
  }
}
