import { Injectable } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from './api.service';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { ProductoPublico, CategoriaRef, TallaRef } from '../models/producto';
import {
  CotizacionEnvio,
  MetodoEntrega,
  MetodoPago,
  PedidoPublico,
  CheckoutPayload,
  ZonaEnvioPublica,
} from '../models/pedido';
import { BannerPublico, UbicacionBanner } from '../models/banner';
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

  banners(ubicacion?: UbicacionBanner): Observable<ApiResponse<BannerPublico[]>> {
    const query = ubicacion ? `?ubicacion=${ubicacion}` : '';
    return this.get<ApiResponse<BannerPublico[]>>(`/v1/store/banners${query}`);
  }

  metodosEntrega(): Observable<ApiResponse<MetodoEntrega[]>> {
    return this.get<ApiResponse<MetodoEntrega[]>>('/v1/store/metodos-entrega');
  }

  metodosPago(): Observable<ApiResponse<MetodoPago[]>> {
    return this.get<ApiResponse<MetodoPago[]>>('/v1/store/metodos-pago');
  }

  metodosPagoDetalle(): Observable<ApiResponse<MetodoPago[]>> {
    return this.get<ApiResponse<MetodoPago[]>>('/v1/store/metodos-pago/detalle');
  }

  modalidadesEntrega(): Observable<ApiResponse<MetodoEntrega[]>> {
    return this.get<ApiResponse<MetodoEntrega[]>>('/v1/store/modalidades-entrega');
  }

  zonasEnvio(): Observable<ApiResponse<ZonaEnvioPublica[]>> {
    return this.get<ApiResponse<ZonaEnvioPublica[]>>('/v1/store/zonas-envio');
  }

  cotizarEnvio(
    metodoEntregaId: number,
    zonaEnvioId: number | null,
    subtotal: number,
  ): Observable<ApiResponse<CotizacionEnvio>> {
    const query = new URLSearchParams();
    query.set('metodo_entrega_id', String(metodoEntregaId));
    if (zonaEnvioId) {
      query.set('zona_envio_id', String(zonaEnvioId));
    }
    query.set('subtotal', String(subtotal));

    return this.get<ApiResponse<CotizacionEnvio>>(`/v1/store/cotizar-envio?${query.toString()}`);
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
    // Con índice explícito: en PHP cada "[]" abre un elemento nuevo, así que
    // sin índice el par producto_id/precio queda partido en dos entradas y
    // la validación del pedido rechaza el envío con comprobante.
    (payload.precios_esperados ?? []).forEach((esperado, indice) => {
      form.append(`precios_esperados[${indice}][producto_id]`, String(esperado.producto_id));
      form.append(`precios_esperados[${indice}][precio]`, String(esperado.precio));
    });
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
    if (payload.zona_envio_id) {
      form.append('zona_envio_id', String(payload.zona_envio_id));
    }
    if (payload.cupon_codigo) {
      form.append('cupon_codigo', payload.cupon_codigo);
    }
    form.append('comprobante', comprobante, comprobante.name);
    return this.post<RespuestaCheckout>(url, form);
  }

  clienteExiste(telefono: string, email: string | null): Observable<boolean> {
    const params: Record<string, string> = {};
    if (telefono.trim()) {
      params['telefono'] = telefono.trim();
    }
    if (email?.trim()) {
      params['email'] = email.trim();
    }
    return this.get<{ existe: boolean }>(
      `/v1/store/clientes/existe?${new URLSearchParams(params).toString()}`,
    ).pipe(map((res) => res.existe ?? false));
  }

  seguirPedido(numeroPedido: string): Observable<ApiResponse<PedidoPublico>> {
    return this.get<ApiResponse<PedidoPublico>>(
      `/v1/store/pedidos/${encodeURIComponent(numeroPedido)}?t=${Date.now()}`,
    );
  }
}