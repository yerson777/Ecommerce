import { ProductoPublico } from '../models/producto';

interface FormatoPrecio {
  simbolo: string;
  decimales: number;
  separadorMiles: string;
  separadorDecimal: string;
}

let formato: FormatoPrecio = {
  simbolo: 'Bs',
  decimales: 2,
  separadorMiles: '',
  separadorDecimal: '.',
};

/** Aplica el formato configurado desde el panel de administración. */
export function configurarFormatoPrecio(nuevo: Partial<FormatoPrecio>): void {
  formato = {
    simbolo: nuevo.simbolo?.trim() || 'Bs',
    decimales: Math.max(0, Math.min(4, Math.trunc(nuevo.decimales ?? 2))),
    separadorMiles: nuevo.separadorMiles ?? '',
    separadorDecimal: nuevo.separadorDecimal || '.',
  };
}

export function formatearPrecio(valor: number | string): string {
  const numero = typeof valor === 'string' ? Number(valor) : valor;

  if (Number.isNaN(numero)) {
    return `${formato.simbolo} ${formatearNumero(0)}`;
  }

  return `${formato.simbolo} ${formatearNumero(numero)}`;
}

function formatearNumero(numero: number): string {
  const signo = numero < 0 ? '-' : '';
  const [entero, fraccion] = Math.abs(numero).toFixed(formato.decimales).split('.');
  const enteroAgrupado = formato.separadorMiles
    ? entero.replace(/\B(?=(\d{3})+(?!\d))/g, formato.separadorMiles)
    : entero;

  return `${signo}${enteroAgrupado}${fraccion ? `${formato.separadorDecimal}${fraccion}` : ''}`;
}

/** Precio a cobrar: el de oferta si existe, si no el precio base. */
export function precioEfectivo(producto: ProductoPublico): string {
  return producto.precio_oferta ?? producto.precio;
}

export function tieneOferta(producto: ProductoPublico): boolean {
  return producto.precio_oferta !== null && producto.precio_oferta !== undefined;
}

/** Porcentaje de descuento (el backend lo deriva de la oferta). */
export function porcentajeDescuento(producto: ProductoPublico): number | null {
  return producto.descuento_porcentaje ?? null;
}
