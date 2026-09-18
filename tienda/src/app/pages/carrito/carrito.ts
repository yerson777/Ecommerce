import { Component, Signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { CartService, CartItem } from '../../core/services/cart.service';
import { formatearPrecio } from '../../core/utils/precio';

@Component({
  imports: [CommonModule, RouterLink],
  selector: 'app-carrito',
  styleUrl: './carrito.scss',
  templateUrl: './carrito.html',
})
export class CarritoComponent {
  private readonly cartService: CartService;
  readonly items: Signal<CartItem[]>;
  readonly subtotal: Signal<number>;

  constructor(cartService: CartService) {
    this.cartService = cartService;
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
}