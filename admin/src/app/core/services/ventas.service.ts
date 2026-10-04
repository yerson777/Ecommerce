import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { Paginated } from '../models/paginated';
import { MetodoPagoRef } from '../models/pago';
import { Venta, VentaFiltros, VentaPresencialPayload } from '../models/venta';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class VentasService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(filtros: VentaFiltros): Observable<ApiResponse<Paginated<Venta>>> {
    return this.get<ApiResponse<Paginated<Venta>>>(
      `/v1/admin/ventas${toQueryString({ ...filtros })}`,
    );
  }

  detalle(id: number): Observable<ApiResponse<Venta>> {
    return this.get<ApiResponse<Venta>>(`/v1/admin/ventas/${id}`);
  }

  crear(payload: VentaPresencialPayload, comprobante: File | null = null): Observable<ApiResponse<Venta>> {
    const formData = new FormData();
    formData.append('cliente_id', String(payload.cliente_id));
    payload.productos.forEach((id) => formData.append('productos[]', String(id)));
    formData.append('metodo_entrega_id', String(payload.metodo_entrega_id));
    formData.append('metodo_pago_id', String(payload.metodo_pago_id));
    if (payload.cupon_codigo) {
      formData.append('cupon_codigo', payload.cupon_codigo);
    }
    if (payload.descuento != null && payload.descuento > 0) {
      formData.append('descuento', String(payload.descuento));
    }
    if (payload.monto_pagado != null && payload.monto_pagado > 0) {
      formData.append('monto_pagado', String(payload.monto_pagado));
    }
    if (payload.referencia) {
      formData.append('referencia', payload.referencia);
    }
    if (payload.notas) {
      formData.append('notas', payload.notas);
    }
    if (comprobante) {
      formData.append('comprobante', comprobante);
    }

    return this.postFormData<ApiResponse<Venta>>('/v1/admin/ventas', formData);
  }

  anular(id: number): Observable<ApiResponse<Venta>> {
    return this.post<ApiResponse<Venta>>(`/v1/admin/ventas/${id}/anular`, {});
  }

  metodosPago(): Observable<ApiResponse<MetodoPagoRef[]>> {
    return this.get<ApiResponse<MetodoPagoRef[]>>('/v1/admin/ventas/metodos-pago');
  }
}