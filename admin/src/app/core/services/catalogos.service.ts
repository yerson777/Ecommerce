import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import {
  Categoria,
  CategoriaPayload,
  Condicion,
  CondicionPayload,
  Marca,
  MarcaPayload,
  Talla,
  TallaPayload,
} from '../models/catalogo';
import { CategoriaRef, CondicionRef, MarcaRef, TallaRef } from '../models/producto';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class CatalogosService extends ApiService {
  constructor(http: HttpClient) {
    super(http);
  }

  // --- Lecturas (catálogos para selects) ---

  categorias(): Observable<ApiResponse<CategoriaRef[]>> {
    return this.get<ApiResponse<CategoriaRef[]>>('/v1/admin/categorias');
  }

  tallas(): Observable<ApiResponse<TallaRef[]>> {
    return this.get<ApiResponse<TallaRef[]>>('/v1/admin/tallas');
  }

  marcas(): Observable<ApiResponse<MarcaRef[]>> {
    return this.get<ApiResponse<MarcaRef[]>>('/v1/admin/marcas');
  }

  condiciones(): Observable<ApiResponse<CondicionRef[]>> {
    return this.get<ApiResponse<CondicionRef[]>>('/v1/admin/condiciones');
  }

  // --- Categorías ---

  crearCategoria(archivo: File | null, datos: CategoriaPayload): Observable<ApiResponse<Categoria>> {
    const formData = new FormData();
    if (archivo) {
      formData.append('imagen', archivo);
    }
    this.agregarCategoria(formData, datos);

    return this.postFormData<ApiResponse<Categoria>>('/v1/admin/categorias', formData);
  }

  actualizarCategoria(id: number, archivo: File | null, datos: CategoriaPayload): Observable<ApiResponse<Categoria>> {
    const formData = new FormData();
    if (archivo) {
      formData.append('imagen', archivo);
    }
    this.agregarCategoria(formData, datos);

    return this.putFormData<ApiResponse<Categoria>>(`/v1/admin/categorias/${id}`, formData);
  }

  eliminarCategoria(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/categorias/${id}`);
  }

  moverProductosCategoria(id: number, categoriaId: number): Observable<ApiResponse<{ movidos: number }>> {
    return this.post<ApiResponse<{ movidos: number }>>(`/v1/admin/categorias/${id}/mover-productos`, {
      categoria_id: categoriaId,
    });
  }

  private agregarCategoria(formData: FormData, datos: CategoriaPayload): void {
    formData.append('nombre', datos.nombre);
    if (datos.slug) {
      formData.append('slug', datos.slug);
    }
    formData.append('parent_id', datos.parent_id != null ? String(datos.parent_id) : '');
    formData.append('activo', datos.activo ? '1' : '0');
    formData.append('orden', String(datos.orden));
  }

  // --- Marcas ---

  crearMarca(archivo: File | null, datos: MarcaPayload): Observable<ApiResponse<Marca>> {
    const formData = new FormData();
    if (archivo) {
      formData.append('logo', archivo);
    }
    this.agregarMarca(formData, datos);

    return this.postFormData<ApiResponse<Marca>>('/v1/admin/marcas', formData);
  }

  actualizarMarca(id: number, archivo: File | null, datos: MarcaPayload): Observable<ApiResponse<Marca>> {
    const formData = new FormData();
    if (archivo) {
      formData.append('logo', archivo);
    }
    this.agregarMarca(formData, datos);

    return this.putFormData<ApiResponse<Marca>>(`/v1/admin/marcas/${id}`, formData);
  }

  eliminarMarca(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/marcas/${id}`);
  }

  // --- Tallas ---

  crearTalla(datos: TallaPayload): Observable<ApiResponse<Talla>> {
    return this.post<ApiResponse<Talla>>('/v1/admin/tallas', datos);
  }

  actualizarTalla(id: number, datos: TallaPayload): Observable<ApiResponse<Talla>> {
    return this.put<ApiResponse<Talla>>(`/v1/admin/tallas/${id}`, datos);
  }

  eliminarTalla(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/tallas/${id}`);
  }

  // --- Condiciones ---

  crearCondicion(datos: CondicionPayload): Observable<ApiResponse<Condicion>> {
    return this.post<ApiResponse<Condicion>>('/v1/admin/condiciones', datos);
  }

  actualizarCondicion(id: number, datos: CondicionPayload): Observable<ApiResponse<Condicion>> {
    return this.put<ApiResponse<Condicion>>(`/v1/admin/condiciones/${id}`, datos);
  }

  eliminarCondicion(id: number): Observable<ApiResponse<null>> {
    return this.delete<ApiResponse<null>>(`/v1/admin/condiciones/${id}`);
  }

  // --- Utilidades ---

  private agregarMarca(formData: FormData, datos: MarcaPayload): void {
    formData.append('nombre', datos.nombre);
    if (datos.slug) {
      formData.append('slug', datos.slug);
    }
    formData.append('activo', datos.activo ? '1' : '0');
    formData.append('orden', String(datos.orden));
  }
}
