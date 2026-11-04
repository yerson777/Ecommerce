import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartService } from '../../core/services/cart.service';
import { CartToastService } from '../../core/services/cart-toast.service';

@Component({
  imports: [RouterLink],
  selector: 'app-cart-toast',
  styleUrl: './cart-toast.scss',
  templateUrl: './cart-toast.html',
})
export class CartToastComponent {
  readonly toast = inject(CartToastService);
  readonly carrito = inject(CartService);
}
