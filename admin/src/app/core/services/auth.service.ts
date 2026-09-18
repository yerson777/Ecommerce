import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse, AuthUser, LoginResponse } from '../models/api-response';
import { ApiService } from './api.service';
import { TokenService } from './token.service';

@Injectable({ providedIn: 'root' })
export class AuthService extends ApiService {
  readonly usuario = signal<AuthUser | null>(null);

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

  me(): Observable<ApiResponse<{ user: AuthUser }>> {
    return this.get<ApiResponse<{ user: AuthUser }>>('/v1/admin/auth/me');
  }

  saveToken(token: string): void {
    this.tokenService.setToken(token);
  }

  iniciarSesion(res: LoginResponse): void {
    this.saveToken(res.token);
    this.usuario.set(res.user);
  }

  cargarUsuario(): void {
    this.me().subscribe({
      next: (res) => {
        if (res.success && res.data?.user) {
          this.usuario.set(res.data.user);
        }
      },
      error: () => {
        this.usuario.set(null);
      },
    });
  }

  cerrarSesion(): void {
    this.logout().subscribe({
      next: () => this.finalizarSesion(),
      error: () => this.finalizarSesion(),
    });
  }

  private finalizarSesion(): void {
    this.tokenService.clearToken();
    this.usuario.set(null);
  }
}