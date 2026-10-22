import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { ProductoPublico, CategoriaRef, TallaRef } from '../models/producto';
import { MetodoEntrega, MetodoPago, PedidoPublico, CheckoutPayload } from '../models/pedido';
import { BannerPublico } from '../models/banner';
import { OfertaPublica } from '../models/oferta';
import { ValidarCuponResultado } from '../models/cupon';

/** El POST del checkout agrega "avisos" a la raíz del JSON (precios cambiados). */
export type RespuestaCheckout = ApiResponse<PedidoPublico> & { avisos?: string[] };

@Injectable({ providedIn: 'root' })
export class CatalogoService extends ApiService {
  ping(): Observable<ApiResponse<null>> {
    return this.get<ApiResponse<null>>('/ping');
  }

  listar(params: {
    categoria?: number | null;
    talla?: number | null;
    busqueda?: string | null;
    oferta?: number | null;
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
    if (params.oferta !== null && params.oferta !== undefined) {
      query.set('oferta', String(params.oferta));
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

  /** Oferta vigente (para el encabezado del catálogo desde un banner). */
  oferta(id: number): Observable<ApiResponse<OfertaPublica>> {
    return this.get<ApiResponse<OfertaPublica>>(`/v1/store/ofertas/${id}`);
  }

  relacionados(id: number, limit = 8): Observable<ApiResponse<ProductoPublico[]>> {
    return this.get<ApiResponse<ProductoPublico[]>>(`/v1/store/products/${id}/related?limit=${limit}`);
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

  validarCupon(
    codigo: string,
    subtotal: number,
    opciones?: { productos?: number[]; telefono?: string | null; email?: string | null },
  ): Observable<ApiResponse<ValidarCuponResultado>> {
    const query = new URLSearchParams();
    query.set('codigo', codigo.trim().toUpperCase());
    query.set('subtotal', String(subtotal));
    for (const id of opciones?.productos ?? []) {
      query.append('productos[]', String(id));
    }
    const telefono = opciones?.telefono?.trim();
    if (telefono) {
      query.set('telefono', telefono);
    }
    const email = opciones?.email?.trim();
    if (email) {
      query.set('email', email);
    }
    return this.get<ApiResponse<ValidarCuponResultado>>(
      `/v1/store/cupones/validar?${query.toString()}`,
    );
  }

  crearPedido(payload: CheckoutPayload, comprobante?: File | null): Observable<RespuestaCheckout> {
    const url = '/v1/store/pedidos';

    if (!comprobante) {
      return this.post<RespuestaCheckout>(url, payload);
    }

    const form = new FormData();
    for (const id of payload.productos) {
      form.append('productos[]', String(id));
    }
    for (const esperado of payload.precios_esperados ?? []) {
      form.append('precios_esperados[][producto_id]', String(esperado.producto_id));
      form.append('precios_esperados[][precio]', String(esperado.precio));
    }
    form.append('nombre', payload.nombre);
    form.append('telefono', payload.telefono);
    if (payload.email) {
      form.append('email', payload.email);
    }
    form.append('ciudad', payload.ciudad);
    form.append('direccion', payload.direccion);
    if (payload.notas) {
      form.append('notas', payload.notas);
    }
    form.append('metodo_pago_id', String(payload.metodo_pago_id ?? ''));
    form.append('metodo_entrega_id', String(payload.metodo_entrega_id ?? ''));
    if (payload.codigo_cupon) {
      form.append('codigo_cupon', payload.codigo_cupon);
    }
    form.append('comprobante', comprobante, comprobante.name);
    return this.post<RespuestaCheckout>(url, form);
  }

  seguirPedido(numeroPedido: string): Observable<ApiResponse<PedidoPublico>> {
    return this.get<ApiResponse<PedidoPublico>>(
      `/v1/store/pedidos/${encodeURIComponent(numeroPedido)}`,
    );
  }
}