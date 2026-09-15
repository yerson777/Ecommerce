import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse, LoginResponse } from '../models/api-response';
import { ApiService } from './api.service';
import { TokenService } from './token.service';

@Injectable({ providedIn: 'root' })
export class AuthService extends ApiService {
  constructor(
    http: HttpClient,
    private readonly tokenService: TokenService,
  ) {
    super(http);
  }

  login(email: string, password: string): Observable<ApiResponse<LoginResponse>> {
    return this.post<ApiResponse<LoginResponse>>('/v1/admin/auth/login', { email, password });
  }

  logout(): Observable<ApiResponse<null>> {
    return this.post<ApiResponse<null>>('/v1/admin/auth/logout', {});
  }

  me(): Observable<ApiResponse<{ user: unknown }>> {
    return this.get<ApiResponse<{ user: unknown }>>('/v1/admin/auth/me');
  }

  saveToken(token: string): void {
    this.tokenService.setToken(token);
  }
}