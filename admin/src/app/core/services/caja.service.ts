import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import {
  CategoriaGastoRef,
  FlujoCaja,
  Gasto,
  GastoPayload,
  IngresoManualPayload,
  Movimiento,
  MovimientoFiltros,
  PeriodoFlujo,
  SaldoCaja,
} from '../models/caja';
import { Paginated } from '../models/paginated';
import { ApiService } from './api.service';
import { toQueryString } from '../utils/query';

@Injectable({ providedIn: 'root' })
export class CajaService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  saldo(): Observable<ApiResponse<SaldoCaja>> {
    return this.get<ApiResponse<SaldoCaja>>('/v1/admin/caja/saldo');
  }

  flujo(periodo: PeriodoFlujo): Observable<ApiResponse<FlujoCaja>> {
    return this.get<ApiResponse<FlujoCaja>>(`/v1/admin/caja/flujo?periodo=${periodo}`);
  }

  listar(filtros: MovimientoFiltros): Observable<ApiResponse<Paginated<Movimiento>>> {
    return this.get<ApiResponse<Paginated<Movimiento>>>(
      `/v1/admin/caja/movimientos${toQueryString({ ...filtros })}`,
    );
  }

  categoriasGasto(): Observable<ApiResponse<CategoriaGastoRef[]>> {
    return this.get<ApiResponse<CategoriaGastoRef[]>>('/v1/admin/gastos/categorias');
  }

  registrarGasto(payload: GastoPayload): Observable<ApiResponse<Gasto>> {
    return this.post<ApiResponse<Gasto>>('/v1/admin/gastos', payload);
  }

  registrarIngreso(payload: IngresoManualPayload): Observable<ApiResponse<Movimiento>> {
    return this.post<ApiResponse<Movimiento>>('/v1/admin/caja/movimientos', payload);
  }

  eliminarGasto(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/gastos/${id}`);
  }

  eliminarMovimiento(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/caja/movimientos/${id}`);
  }
}