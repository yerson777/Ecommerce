import { EstadoProducto, ProductoPublico } from '../models/producto';

export function imagenPrincipal(producto: ProductoPublico): string | null {
  const principal = producto.imagenes.find((imagen) => imagen.es_principal);
  return principal?.url ?? producto.imagenes[0]?.url ?? null;
}

export function esDisponible(producto: ProductoPublico): boolean {
  return producto.estado === 'disponible';
}

export function estadoTexto(estado: EstadoProducto): string {
  switch (estado) {
    case 'reservada':
      return 'Reservada';
    case 'vendida':
      return 'Vendida';
    default:
      return 'Disponible';
  }
}
