import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { ApiResponse } from '../models/api-response';

@Injectable({ providedIn: 'root' })
export class CatalogoService extends ApiService {
  ping(): Observable<ApiResponse<null>> {
    return this.get<ApiResponse<null>>('/ping');
  }
}