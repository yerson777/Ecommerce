import { CategoriaRef, CondicionRef, MarcaRef, TallaRef } from './producto';

export type Categoria = CategoriaRef;
export type Marca = MarcaRef;
export type Talla = TallaRef;
export type Condicion = CondicionRef;

export interface CategoriaPayload {
  nombre: string;
  slug?: string | null;
  parent_id: number | null;
  activo: boolean;
  orden: number;
}

export interface MarcaPayload {
  nombre: string;
  slug?: string | null;
  activo: boolean;
  orden: number;
}

export interface TallaPayload {
  nombre: string;
  activo: boolean;
  orden: number;
}

export interface CondicionPayload {
  nombre: string;
  activo: boolean;
  orden: number;
}

export interface CategoriaSinPadre {
  id: number;
  nombre: string;
}
