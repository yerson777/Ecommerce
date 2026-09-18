import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { ProductoPublico, CategoriaRef, TallaRef } from '../models/producto';
import { MetodoEntrega, MetodoPago, PedidoPublico, CheckoutPayload } from '../models/pedido';
import { BannerPublico } from '../models/banner';

@Injectable({ providedIn: 'root' })
export class CatalogoService extends ApiService {
  ping(): Observable<ApiResponse<null>> {
    return this.get<ApiResponse<null>>('/ping');
  }

  listar(params: {
    categoria?: number | null;
    talla?: number | null;
    busqueda?: string | null;
    page?: number;
    per_page?: number;
  }): Observable<ApiResponse<Paginated<ProductoPublico>>> {
    const query = new URLSearchParams();
    if (params.categoria !== null && params.categoria !== undefined) {
      query.set('categoria', String(params.categoria));
    }
    if (params.talla !== null && params.talla !== undefined) {
      query.set('talla', String(params.talla));
    }
    if (params.busqueda) {
      query.set('busqueda', params.busqueda);
    }
    query.set('page', String(params.page ?? 1));
    query.set('per_page', String(params.per_page ?? 12));
    return this.get<ApiResponse<Paginated<ProductoPublico>>>(
      `/v1/store/products?${query.toString()}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<ProductoPublico>> {
    return this.get<ApiResponse<ProductoPublico>>(`/v1/store/products/${id}`);
  }

  categorias(): Observable<ApiResponse<CategoriaRef[]>> {
    return this.get<ApiResponse<CategoriaRef[]>>('/v1/store/categories');
  }

  tallas(): Observable<ApiResponse<TallaRef[]>> {
    return this.get<ApiResponse<TallaRef[]>>('/v1/store/sizes');
  }

  banners(): Observable<ApiResponse<BannerPublico[]>> {
    return this.get<ApiResponse<BannerPublico[]>>('/v1/store/banners');
  }

  metodosEntrega(): Observable<ApiResponse<MetodoEntrega[]>> {
    return this.get<ApiResponse<MetodoEntrega[]>>('/v1/store/metodos-entrega');
  }

  metodosPago(): Observable<ApiResponse<MetodoPago[]>> {
    return this.get<ApiResponse<MetodoPago[]>>('/v1/store/metodos-pago');
  }

  crearPedido(payload: CheckoutPayload): Observable<ApiResponse<PedidoPublico>> {
    return this.post<ApiResponse<PedidoPublico>>('/v1/store/pedidos', payload);
  }

  seguirPedido(numeroPedido: string): Observable<ApiResponse<PedidoPublico>> {
    return this.get<ApiResponse<PedidoPublico>>(
      `/v1/store/pedidos/${encodeURIComponent(numeroPedido)}`,
    );
  }
}