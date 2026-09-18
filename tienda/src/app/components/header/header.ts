import { Component, HostListener, inject, signal, Signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter } from 'rxjs';
import { CartService } from '../../core/services/cart.service';

@Component({
  imports: [CommonModule, RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.scss',
  templateUrl: './header.html',
})
export class Header {
  readonly conteo: Signal<number>;
  readonly menuAbierto = signal(false);

  private readonly router = inject(Router);

  constructor(cartService: CartService) {
    this.conteo = cartService.conteo;

    this.router.events
      .pipe(filter((evento) => evento instanceof NavigationEnd))
      .subscribe(() => this.menuAbierto.set(false));
  }

  alternarMenu(): void {
    this.menuAbierto.set(!this.menuAbierto());
  }

  cerrarMenu(): void {
    this.menuAbierto.set(false);
  }

  @HostListener('window:resize')
  alRedimensionar(): void {
    if (window.innerWidth > 860) {
      this.cerrarMenu();
    }
  }
}