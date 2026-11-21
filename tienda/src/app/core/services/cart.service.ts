import { Injectable, signal, computed } from '@angular/core';

export interface CartItem {
  producto_id: number;
  codigo: string;
  nombre: string;
  color: string | null;
  talla: string | null;
  /** Precio a cobrar (con oferta si la había al agregarlo). */
  precio: string;
  /** Precio base tachado; solo se llena si había oferta. */
  precio_original?: string | null;
  descuento_porcentaje?: number | null;
  imagen: string | null;
}

export interface CuponAplicado {
  codigo: string;
  descuento: number;
}

const STORAGE_KEY = 'everly_cart_v1';
const CUPON_STORAGE_KEY = 'everly_cupon_v1';

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly state = signal<CartItem[]>(this.leerPersistido());
  readonly items = this.state.asReadonly();

  readonly conteo = computed(() => this.state().length);

  readonly subtotal = computed(() =>
    this.state().reduce((acc, item) => acc + Number(item.precio), 0),
  );

  private readonly cupon = signal<CuponAplicado | null>(this.leerCuponPersistido());
  readonly cuponBorrador = signal('');
  readonly cuponAplicado = computed(() => this.cupon() !== null);
  readonly cuponCodigo = computed(() => this.cupon()?.codigo ?? '');
  readonly cuponDescuento = computed(() => this.cupon()?.descuento ?? 0);

  contiene(id: number): boolean {
    return this.state().some((item) => item.producto_id === id);
  }

  agregar(item: CartItem): boolean {
    if (this.contiene(item.producto_id)) {
      return false;
    }
    this.state.update((actual) => [...actual, item]);
    this.persistir();
    this.limpiarCupon();
    return true;
  }

  quitar(productoId: number): void {
    this.state.update((actual) =>
      actual.filter((item) => item.producto_id !== productoId),
    );
    this.persistir();
    this.limpiarCupon();
  }

  vaciar(): void {
    this.state.set([]);
    this.persistir();
    this.limpiarCupon();
  }

  aplicarCupon(codigo: string, descuento: number): void {
    this.cupon.set({ codigo, descuento });
    this.cuponBorrador.set('');
    this.persistirCupon();
  }

  limpiarCupon(): void {
    this.cupon.set(null);
    this.cuponBorrador.set('');
    this.persistirCupon();
  }

  private persistir(): void {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state()));
    } catch {
      // Sin persistencia no bloqueamos el carrito en memoria
    }
  }

  private persistirCupon(): void {
    try {
      localStorage.setItem(CUPON_STORAGE_KEY, JSON.stringify(this.cupon()));
    } catch {
      // Sin persistencia no bloqueamos el cupón en memoria
    }
  }

  private leerPersistido(): CartItem[] {
    try {
      const crudo = localStorage.getItem(STORAGE_KEY);
      if (!crudo) {
        return [];
      }
      const datos: unknown = JSON.parse(crudo);
      if (!Array.isArray(datos)) {
        return [];
      }
      return datos.filter(
        (item): item is CartItem =>
          typeof item === 'object' &&
          item !== null &&
          'producto_id' in item &&
          'precio' in item,
      );
    } catch {
      return [];
    }
  }

  private leerCuponPersistido(): CuponAplicado | null {
    try {
      const crudo = localStorage.getItem(CUPON_STORAGE_KEY);
      if (!crudo) {
        return null;
      }
      const datos: unknown = JSON.parse(crudo);
      if (
        datos &&
        typeof datos === 'object' &&
        'codigo' in datos &&
        typeof (datos as { codigo: unknown }).codigo === 'string' &&
        'descuento' in datos &&
        typeof (datos as { descuento: unknown }).descuento === 'number'
      ) {
        return datos as CuponAplicado;
      }
      return null;
    } catch {
      return null;
    }
  }
}
