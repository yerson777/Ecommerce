import { ProductoPublico } from '../models/producto';

export function formatearPrecio(valor: number | string): string {
  const numero = typeof valor === 'string' ? Number(valor) : valor;
  if (Number.isNaN(numero)) {
    return 'Bs 0.00';
  }
  return `Bs ${numero.toFixed(2)}`;
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