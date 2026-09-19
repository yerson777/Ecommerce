import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, map } from 'rxjs';
import { environment } from '../../../environments/environment';

interface JsonData {
  success: boolean;
  message: string;
  data: unknown;
  meta?: Record<string, unknown> | null;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  protected readonly baseUrl = environment.apiUrl;

  constructor(protected readonly http: HttpClient) {}

  protected get<T>(path: string): Observable<T> {
    return this.http
      .get<JsonData>(`${this.baseUrl}${path}`)
      .pipe(map((response) => this.adaptarPaginado(response) as T));
  }

  protected post<T>(path: string, body: unknown): Observable<T> {
    return this.http.post<T>(`${this.baseUrl}${path}`, body);
  }

  protected put<T>(path: string, body: unknown): Observable<T> {
    return this.http.put<T>(`${this.baseUrl}${path}`, body);
  }

  protected patch<T>(path: string, body: unknown): Observable<T> {
    return this.http.patch<T>(`${this.baseUrl}${path}`, body);
  }

  protected delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(`${this.baseUrl}${path}`);
  }

  protected postFormData<T>(path: string, formData: FormData): Observable<T> {
    return this.http.post<T>(`${this.baseUrl}${path}`, formData);
  }

  protected putFormData<T>(path: string, formData: FormData): Observable<T> {
    return this.http.post<T>(`${this.baseUrl}${path}`, formData);
  }

  private adaptarPaginado(response: JsonData): JsonData {
    const data = response.data;
    const meta = response.meta;

    if (Array.isArray(data) && meta && typeof meta === 'object' && 'last_page' in meta) {
      const pagina = meta as {
        current_page?: number;
        last_page?: number;
        per_page?: number;
        total?: number;
      };

      return {
        success: response.success,
        message: response.message,
        data: {
          data,
          current_page: pagina.current_page ?? 1,
          last_page: pagina.last_page ?? 1,
          per_page: pagina.per_page ?? data.length,
          total: pagina.total ?? 0,
        },
      };
    }

    return response;
  }
}