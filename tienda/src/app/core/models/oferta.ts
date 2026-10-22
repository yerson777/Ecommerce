export type TipoOfertaPublica = 'porcentaje' | 'fijo';

export interface OfertaPublica {
  id: number;
  nombre: string;
  tipo: TipoOfertaPublica;
  valor: string;
}
