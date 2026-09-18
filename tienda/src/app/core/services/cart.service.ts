import { Injectable, signal, computed } from '@angular/core';

export interface CartItem {
  producto_id: number;
  codigo: string;
  nombre: string;
  color: string | null;
  talla: string | null;
  precio: string;
  imagen: string | null;
}

const STORAGE_KEY = 'everly_cart_v1';

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly state = signal<CartItem[]>(this.leerPersistido());
  readonly items = this.state.asReadonly();

  readonly conteo = computed(() => this.state().length);

  readonly subtotal = computed(() =>
    this.state().reduce((acc, item) => acc + Number(item.precio), 0),
  );

  contiene(id: number): boolean {
    return this.state().some((item) => item.producto_id === id);
  }

  agregar(item: CartItem): boolean {
    if (this.contiene(item.producto_id)) {
      return false;
    }
    this.state.update((actual) => [...actual, item]);
    this.persistir();
    return true;
  }

  quitar(productoId: number): void {
    this.state.update((actual) =>
      actual.filter((item) => item.producto_id !== productoId),
    );
    this.persistir();
  }

  vaciar(): void {
    this.state.set([]);
    this.persistir();
  }

  private persistir(): void {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state()));
    } catch {
      // Sin persistencia no bloqueamos el carrito en memoria
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
}