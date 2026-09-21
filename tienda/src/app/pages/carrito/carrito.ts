import { Component, OnDestroy, Signal, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { CartService, CartItem } from '../../core/services/cart.service';
import { CatalogoService } from '../../core/services/catalogo.service';
import { ApiError } from '../../core/models/api-response';
import { formatearPrecio } from '../../core/utils/precio';

@Component({
  imports: [CommonModule, RouterLink],
  selector: 'app-carrito',
  styleUrl: './carrito.scss',
  templateUrl: './carrito.html',
})
export class CarritoComponent implements OnDestroy {
  private readonly cartService: CartService;
  private readonly catalogo: CatalogoService;
  readonly items: Signal<CartItem[]>;
  readonly subtotal: Signal<number>;

  readonly cuponCodigo = signal('');
  readonly cuponAplicado = signal(false);
  readonly validandoCupon = signal(false);
  readonly cuponMensaje = signal<string | null>(null);
  readonly cuponError = signal(false);
  readonly cuponDescuento = signal<number>(0);

  constructor(cartService: CartService, catalogo: CatalogoService) {
    this.cartService = cartService;
    this.catalogo = catalogo;
    this.items = cartService.items;
    this.subtotal = cartService.subtotal;
  }

  formatearPrecio(valor: number | string): string {
    return formatearPrecio(valor);
  }

  quitar(productoId: number): void {
    this.cartService.quitar(productoId);
  }

  vaciar(): void {
    this.cartService.vaciar();
  }

  limpiarCupon(event: Event): string {
    return (event.target as HTMLInputElement).value.trim().toUpperCase();
  }

  aplicarCupon(): void {
    const codigo = this.cuponCodigo().trim().toUpperCase();
    if (!codigo || this.cuponAplicado() || this.validandoCupon()) {
      return;
    }

    this.validandoCupon.set(true);
    this.cuponMensaje.set(null);
    this.cuponError.set(false);

    this.catalogo.validarCupon(codigo, this.subtotal()).subscribe({
      next: (res) => {
        this.validandoCupon.set(false);
        const datos = res.data;
        if (datos?.valido) {
          this.cuponAplicado.set(true);
          this.cuponDescuento.set(Number(datos.descuento) || 0);
          this.cuponMensaje.set(datos.mensaje ?? 'Cupón aplicado correctamente.');
        } else {
          this.cuponAplicado.set(false);
          this.cuponDescuento.set(0);
          this.cuponError.set(true);
          this.cuponMensaje.set(datos?.mensaje ?? 'El cupón no es válido para este pedido.');
        }
      },
      error: () => {
        this.validandoCupon.set(false);
        this.cuponAplicado.set(false);
        this.cuponDescuento.set(0);
        this.cuponError.set(true);
        this.cuponMensaje.set('No pudimos validar el cupón. Intentá de nuevo.');
      },
    });
  }

  totalConDescuento(): number {
    return Math.max(0, this.subtotal() - this.cuponDescuento());
  }

  ngOnDestroy(): void {
    const cuponGuardar = this.cuponAplicado()
      ? { codigo: this.cuponCodigo(), descuento: this.cuponDescuento() }
      : null;
    try {
      sessionStorage.setItem(
        'everly_cupon_carrito',
        JSON.stringify(cuponGuardar),
      );
    } catch {
      // Sin persistencia no bloqueamos la navegación.
    }
  }
}
