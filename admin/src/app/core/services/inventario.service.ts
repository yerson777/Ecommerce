import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { InventarioResumen } from '../models/dashboard';
import { Paginated } from '../models/paginated';
import { EstadoPrenda, Producto } from '../models/producto';
import { toQueryString } from '../utils/query';
import { ApiService } from './api.service';

export interface VenderPayload {
  producto_id: number;
  cliente_id: number;
  costo_envio: number;
  notas?: string | null;
}

export interface InventarioFiltros {
  busqueda?: string;
  estado?: EstadoPrenda | null;
  categoria?: number | null;
  talla?: number | null;
  condicion?: number | null;
  publicado?: boolean | null;
  verificada?: boolean | null;
  dias_min?: number | null;
  dias_max?: number | null;
  orden?: string;
  page?: number;
  per_page?: number;
}

@Injectable({ providedIn: 'root' })
export class InventarioService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  resumen(): Observable<ApiResponse<InventarioResumen>> {
    return this.get<ApiResponse<InventarioResumen>>('/v1/admin/inventario');
  }

  listado(filtros: InventarioFiltros): Observable<ApiResponse<Paginated<Producto>>> {
    return this.get<ApiResponse<Paginated<Producto>>>(
      `/v1/admin/inventario/listado${toQueryString({ ...filtros })}`,
    );
  }

  sinRotacion(dias: number, page = 1, perPage = 20): Observable<ApiResponse<Paginated<Producto>>> {
    return this.get<ApiResponse<Paginated<Producto>>>(
      `/v1/admin/inventario/sin-rotacion${toQueryString({ dias, page, per_page: perPage })}`,
    );
  }

  verificar(productoId: number, verificada: boolean): Observable<ApiResponse<Producto>> {
    return this.post<ApiResponse<Producto>>('/v1/admin/inventario/verificar', {
      producto_id: productoId,
      verificada,
    });
  }

  revision(categoriaId?: number | null): Observable<ApiResponse<{ reiniciadas: number }>> {
    return this.post<ApiResponse<{ reiniciadas: number }>>('/v1/admin/inventario/revision', {
      categoria_id: categoriaId ?? null,
    });
  }

  exportar(filtros: InventarioFiltros): Observable<Blob> {
    return this.http.get(
      `${this.baseUrl}/v1/admin/inventario/exportar${toQueryString({ ...filtros })}`,
      { responseType: 'blob' },
    );
  }

  reservar(productoId: number, venceEn?: string): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>('/v1/admin/inventario/reservar', {
      producto_id: productoId,
      vence_en: venceEn ?? null,
    });
  }

  liberar(productoId: number): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>('/v1/admin/inventario/liberar', {
      producto_id: productoId,
    });
  }

  vender(data: VenderPayload): Observable<ApiResponse<unknown>> {
    return this.post<ApiResponse<unknown>>('/v1/admin/inventario/vender', data);
  }
}
