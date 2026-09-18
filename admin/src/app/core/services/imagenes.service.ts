import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { ProductoImagen } from '../models/producto';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class ImagenesService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  listar(productoId: number): Observable<ApiResponse<ProductoImagen[]>> {
    return this.get<ApiResponse<ProductoImagen[]>>(`/v1/admin/productos/${productoId}/imagenes`);
  }

  subir(productoId: number, archivos: File[]): Observable<ApiResponse<ProductoImagen[]>> {
    const formData = new FormData();

    if (archivos.length === 1) {
      formData.append('imagen', archivos[0]);
    } else {
      for (const archivo of archivos) {
        formData.append('imagenes[]', archivo);
      }
    }

    return this.postFormData<ApiResponse<ProductoImagen[]>>(
      `/v1/admin/productos/${productoId}/imagenes`,
      formData,
    );
  }

  establecerPrincipal(productoId: number, imagenId: number): Observable<ApiResponse<ProductoImagen>> {
    return this.put<ApiResponse<ProductoImagen>>(
      `/v1/admin/productos/${productoId}/imagenes/${imagenId}/principal`,
      {},
    );
  }

  reordenar(productoId: number, ordenes: number[]): Observable<ApiResponse<ProductoImagen[]>> {
    return this.put<ApiResponse<ProductoImagen[]>>(`/v1/admin/productos/${productoId}/imagenes/orden`, {
      ordenes,
    });
  }

  reemplazar(productoId: number, imagenId: number, archivo: File): Observable<ApiResponse<ProductoImagen>> {
    const formData = new FormData();
    formData.append('imagen', archivo);

    return this.putFormData<ApiResponse<ProductoImagen>>(
      `/v1/admin/productos/${productoId}/imagenes/${imagenId}`,
      formData,
    );
  }

  eliminar(productoId: number, imagenId: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/productos/${productoId}/imagenes/${imagenId}`);
  }
}