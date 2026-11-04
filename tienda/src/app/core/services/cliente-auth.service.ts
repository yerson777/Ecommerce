import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, firstValueFrom, tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { borrarTokenCliente, guardarTokenCliente, leerTokenCliente } from '../auth/token-storage';

export interface ClienteCuenta {
  id: number;
  nombre: string;
  email: string;
  telefono: string | null;
  direccion: string | null;
  ciudad: string | null;
  email_verificado: boolean;
  bloqueado: boolean;
  created_at?: string;
}

export interface RegistroDatos {
  nombre: string;
  email: string;
  telefono: string;
  password: string;
  password_confirmation: string;
}

export interface LoginDatos {
  email: string;
  password: string;
}

export interface PerfilDatos {
  nombre?: string;
  telefono?: string | null;
  direccion?: string | null;
  ciudad?: string | null;
}

export interface PasswordDatos {
  actual: string;
  password: string;
  password_confirmation: string;
}

export interface RestablecerDatos {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

interface AuthData {
  token: string;
  cliente: ClienteCuenta;
}

interface AuthResponse {
  success: boolean;
  message: string;
  data: AuthData;
}

interface ClienteResponse {
  success: boolean;
  message: string;
  data: ClienteCuenta;
}

interface MensajeResponse {
  success: boolean;
  message: string;
  data: null;
}

@Injectable({ providedIn: 'root' })
export class ClienteAuthService {
  private readonly http = inject(HttpClient);
  private readonly base = environment.apiUrl;

  private readonly tokenState = signal<string | null>(leerTokenCliente());
  readonly token = this.tokenState.asReadonly();

  private readonly clienteState = signal<ClienteCuenta | null>(null);
  readonly cliente = this.clienteState.asReadonly();

  readonly logueado = computed(() => this.clienteState() !== null);

  /**
   * Restaura la sesión al arrancar la app si hay un token guardado.
   */
  async inicializar(): Promise<void> {
    if (!this.tokenState()) {
      return;
    }

    try {
      const res = await firstValueFrom(
        this.http.get<ClienteResponse>(`${this.base}/v1/store/auth/perfil`),
      );
      if (res?.data) {
        this.clienteState.set(res.data);
      }
    } catch {
      this.limpiarSesionLocal();
    }
  }

  registro(datos: RegistroDatos): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${this.base}/v1/store/auth/registro`, datos)
      .pipe(tap((res) => this.guardarSesion(res.data)));
  }

  login(datos: LoginDatos): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${this.base}/v1/store/auth/login`, datos)
      .pipe(tap((res) => this.guardarSesion(res.data)));
  }

  cerrarSesion(): void {
    if (this.tokenState()) {
      this.http
        .post<MensajeResponse>(`${this.base}/v1/store/auth/logout`, {})
        .subscribe({ error: () => undefined });
    }
    this.limpiarSesionLocal();
  }

  actualizarPerfil(datos: PerfilDatos): Observable<ClienteResponse> {
    return this.http
      .put<ClienteResponse>(`${this.base}/v1/store/auth/perfil`, datos)
      .pipe(
        tap((res) => {
          if (res?.data) {
            this.clienteState.set(res.data);
          }
        }),
      );
  }

  cambiarPassword(datos: PasswordDatos): Observable<MensajeResponse> {
    return this.http.put<MensajeResponse>(`${this.base}/v1/store/auth/password`, datos);
  }

  recuperar(email: string): Observable<MensajeResponse> {
    return this.http.post<MensajeResponse>(`${this.base}/v1/store/auth/recuperar`, { email });
  }

  restablecer(datos: RestablecerDatos): Observable<MensajeResponse> {
    return this.http.post<MensajeResponse>(`${this.base}/v1/store/auth/restablecer`, datos);
  }

  private guardarSesion(data: AuthData): void {
    guardarTokenCliente(data.token);
    this.tokenState.set(data.token);
    this.clienteState.set(data.cliente);
  }

  private limpiarSesionLocal(): void {
    borrarTokenCliente();
    this.tokenState.set(null);
    this.clienteState.set(null);
  }
}
