import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, firstValueFrom } from 'rxjs';
import { ApiResponse, AuthUser, LoginResponse } from '../models/api-response';
import { ApiService } from './api.service';
import { TokenService } from './token.service';

const CACHE_KEY = 'everly_admin_user';

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
    this.setUsuario(res.user);
  }

  cargarUsuario(): Promise<AuthUser | null> {
    const actual = this.usuario();
    if (actual) {
      return Promise.resolve(actual);
    }

    const cache = this.leerCache();
    if (cache) {
      this.usuario.set(cache);
      return Promise.resolve(cache);
    }

    if (!this.tokenService.getToken()) {
      return Promise.resolve(null);
    }

    return firstValueFrom(this.me())
      .then((res) => {
        const usuario = res.success && res.data?.user ? res.data.user : null;
        this.setUsuario(usuario);
        return usuario;
      })
      .catch(() => {
        this.usuario.set(null);
        return null;
      });
  }

  cerrarSesion(): void {
    this.logout().subscribe({
      next: () => this.finalizarSesion(),
      error: () => this.finalizarSesion(),
    });
  }

  private setUsuario(usuario: AuthUser | null): void {
    this.usuario.set(usuario);
    if (usuario) {
      localStorage.setItem(CACHE_KEY, JSON.stringify(usuario));
    } else {
      localStorage.removeItem(CACHE_KEY);
    }
  }

  private leerCache(): AuthUser | null {
    const crudo = localStorage.getItem(CACHE_KEY);
    if (!crudo) {
      return null;
    }
    try {
      return JSON.parse(crudo) as AuthUser;
    } catch {
      localStorage.removeItem(CACHE_KEY);
      return null;
    }
  }

  private finalizarSesion(): void {
    this.tokenService.clearToken();
    this.usuario.set(null);
    localStorage.removeItem(CACHE_KEY);
  }
}