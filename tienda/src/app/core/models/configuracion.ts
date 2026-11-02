export interface MonedaTienda {
  simbolo: string;
  decimales: number;
  separador_miles: string;
  separador_decimal: string;
}

export interface TextosTienda {
  cambios_devoluciones: string | null;
  envios: string | null;
  terminos: string | null;
}

export interface ConfiguracionTienda {
  nombre: string | null;
  logo: string | null;
  logo_url: string | null;
  whatsapp: string | null;
  correo: string | null;
  direccion: string | null;
  horario: string | null;
  instagram: string | null;
  facebook: string | null;
  tiktok: string | null;
  textos: TextosTienda;
  moneda: MonedaTienda;
}
