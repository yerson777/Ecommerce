export interface MetodoPagoConfig {
  id: number;
  nombre: string;
  codigo: string | null;
  instrucciones: string | null;
  titular: string | null;
  banco: string | null;
  numero_cuenta: string | null;
  activo: boolean;
  orden: number;
  qr_url: string | null;
  qr_nombre: string | null;
  qr_mime: string | null;
  qr_tamano: number | null;
  qr_subido_en: string | null;
}

export interface MetodoEntregaConfig {
  id: number;
  nombre: string;
  costo: string;
  activo: boolean;
  orden: number;
}

export interface MonedaConfig {
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
  moneda: MonedaConfig;
}

export interface ConfiguracionHistorial {
  id: number;
  tipo: 'tienda' | 'metodo_pago';
  campo: string;
  campo_etiqueta: string;
  metodo_pago_id: number | null;
  metodo_pago: string | null;
  valor_anterior: string | null;
  valor_nuevo: string | null;
  usuario: string | null;
  created_at: string | null;
}
