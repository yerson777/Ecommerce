import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { Rol, Usuario, UsuarioDatos } from '../models/usuario';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class UsuariosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: { busqueda?: string; rol?: Rol; page?: number; per_page?: number }): Observable<
    ApiResponse<Paginated<Usuario>>
  > {
    return this.get<ApiResponse<Paginated<Usuario>>>(
      `/v1/admin/usuarios${toQueryString({ ...filtros })}`,
    );
  }

  crear(datos: UsuarioDatos): Observable<ApiResponse<Usuario>> {
    return this.post<ApiResponse<Usuario>>('/v1/admin/usuarios', datos);
  }

  actualizar(id: number, datos: Partial<UsuarioDatos>): Observable<ApiResponse<Usuario>> {
    return this.put<ApiResponse<Usuario>>(`/v1/admin/usuarios/${id}`, datos);
  }

  desactivar(id: number): Observable<ApiResponse<Usuario>> {
    return this.delete<ApiResponse<Usuario>>(`/v1/admin/usuarios/${id}`);
  }
}