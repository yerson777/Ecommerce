import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { Cliente, ClienteFiltros } from '../models/cliente';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

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

  opciones(): Observable<ApiResponse<{ id: number; nombre: string; telefono: string | null }[]>> {
    return this.get<ApiResponse<{ id: number; nombre: string; telefono: string | null }[]>>(
      '/v1/admin/clientes/opciones',
    );
  }
}