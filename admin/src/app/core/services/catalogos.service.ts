import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { CategoriaRef, TallaRef } from '../models/producto';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class CatalogosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  categorias(): Observable<ApiResponse<CategoriaRef[]>> {
    return this.get<ApiResponse<CategoriaRef[]>>('/v1/admin/categorias');
  }

  tallas(): Observable<ApiResponse<TallaRef[]>> {
    return this.get<ApiResponse<TallaRef[]>>('/v1/admin/tallas');
  }
}