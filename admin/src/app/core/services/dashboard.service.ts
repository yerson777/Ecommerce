import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { DashboardResumen } from '../models/dashboard';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class DashboardService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  resumen(): Observable<ApiResponse<DashboardResumen>> {
    return this.get<ApiResponse<DashboardResumen>>('/v1/admin/dashboard');
  }
}