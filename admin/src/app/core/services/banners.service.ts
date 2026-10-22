import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { ApiService } from './api.service';
import { Banner, BannerPayload } from '../models/banner';

@Injectable({ providedIn: 'root' })
export class BannersService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(): Observable<ApiResponse<Banner[]>> {
    return this.get<ApiResponse<Banner[]>>('/v1/admin/banners');
  }

  crear(
    archivo: File,
    datos: BannerPayload,
    archivoMovil: File | null = null,
  ): Observable<ApiResponse<Banner>> {
    const formData = new FormData();
    formData.append('imagen', archivo);
    if (archivoMovil) {
      formData.append('imagen_movil', archivoMovil);
    }
    this.agregarDatos(formData, datos);

    return this.postFormData<ApiResponse<Banner>>('/v1/admin/banners', formData);
  }

  actualizar(
    id: number,
    archivo: File | null,
    datos: BannerPayload,
    archivoMovil: File | null = null,
  ): Observable<ApiResponse<Banner>> {
    const formData = new FormData();
    if (archivo) {
      formData.append('imagen', archivo);
    }
    if (archivoMovil) {
      formData.append('imagen_movil', archivoMovil);
    }
    this.agregarDatos(formData, datos);

    return this.putFormData<ApiResponse<Banner>>(`/v1/admin/banners/${id}`, formData);
  }

  reordenar(ordenes: number[]): Observable<ApiResponse<Banner[]>> {
    return this.put<ApiResponse<Banner[]>>('/v1/admin/banners/orden', { ordenes });
  }

  eliminar(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/banners/${id}`);
  }

  private agregarDatos(formData: FormData, datos: BannerPayload): void {
    if (datos.titulo !== null && datos.titulo !== undefined) {
      formData.append('titulo', datos.titulo);
    }
    if (datos.subtitulo !== null && datos.subtitulo !== undefined) {
      formData.append('subtitulo', datos.subtitulo);
    }
    if (datos.enlace_tipo !== undefined && datos.enlace_tipo !== null) {
      formData.append('enlace_tipo', datos.enlace_tipo);
    }
    if (datos.enlace_id !== undefined && datos.enlace_id !== null) {
      formData.append('enlace_id', String(datos.enlace_id));
    }
    if (datos.enlace_url !== undefined && datos.enlace_url !== null) {
      formData.append('enlace_url', datos.enlace_url);
    }
    if (datos.fecha_inicio !== undefined) {
      formData.append('fecha_inicio', datos.fecha_inicio ?? '');
    }
    if (datos.fecha_fin !== undefined) {
      formData.append('fecha_fin', datos.fecha_fin ?? '');
    }
    if (datos.sin_imagen_movil) {
      formData.append('sin_imagen_movil', '1');
    }
    if (datos.activo !== undefined) {
      formData.append('activo', datos.activo ? '1' : '0');
    }
    if (datos.orden !== undefined) {
      formData.append('orden', String(datos.orden));
    }
  }
}
