export interface MetodoPagoConfig {
  id: number;
  nombre: string;
  activo: boolean;
  orden: number;
}

export interface MetodoEntregaConfig {
  id: number;
  nombre: string;
  costo: string;
  activo: boolean;
  orden: number;
}