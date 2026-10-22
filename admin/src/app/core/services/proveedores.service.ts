import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Proveedor, ProveedorDatos, ProveedorFiltros } from '../models/proveedor';
import { toQueryString } from '../utils/query';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class ProveedoresService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: ProveedorFiltros = {}): Observable<ApiResponse<Proveedor[]>> {
    return this.get<ApiResponse<Proveedor[]>>(
      `/v1/admin/proveedores${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Proveedor>> {
    return this.get<ApiResponse<Proveedor>>(`/v1/admin/proveedores/${id}`);
  }

  crear(datos: ProveedorDatos): Observable<ApiResponse<Proveedor>> {
    return this.post<ApiResponse<Proveedor>>('/v1/admin/proveedores', datos);
  }

  actualizar(id: number, datos: ProveedorDatos): Observable<ApiResponse<Proveedor>> {
    return this.put<ApiResponse<Proveedor>>(`/v1/admin/proveedores/${id}`, datos);
  }

  cambiarEstado(id: number, activo: boolean): Observable<ApiResponse<Proveedor>> {
    return this.post<ApiResponse<Proveedor>>(`/v1/admin/proveedores/${id}/estado`, { activo });
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/proveedores/${id}`);
  }
}
