import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { environment } from '../../../environments/environment';
import { MiPedido, MiPedidoDetalle, MiPedidosPagina } from '../models/mi-pedido';

interface ListaResponse {
  success: boolean;
  message: string;
  data: MiPedido[] | null;
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  } | null;
}

interface DetalleResponse {
  success: boolean;
  message: string;
  data: MiPedidoDetalle;
}

@Injectable({ providedIn: 'root' })
export class MiPedidoService {
  private readonly http = inject(HttpClient);
  private readonly base = environment.apiUrl;

  listar(page = 1, perPage = 10): Observable<MiPedidosPagina> {
    return this.http
      .get<ListaResponse>(`${this.base}/v1/store/mis-pedidos`, {
        params: { page, per_page: perPage },
      })
      .pipe(
        map((res) => ({
          items: res.data ?? [],
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
          per_page: res.meta?.per_page ?? perPage,
          total: res.meta?.total ?? 0,
        })),
      );
  }

  detalle(numero: string): Observable<MiPedidoDetalle> {
    return this.http
      .get<DetalleResponse>(`${this.base}/v1/store/mis-pedidos/${encodeURIComponent(numero)}`)
      .pipe(map((res) => res.data));
  }
}
