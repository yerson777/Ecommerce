import { Component, effect, HostListener, inject, OnDestroy, signal, Signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter } from 'rxjs';
import { CartService } from '../../core/services/cart.service';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';
import { ConfiguracionService } from '../../core/services/configuracion.service';

@Component({
  imports: [CommonModule, RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.scss',
  templateUrl: './header.html',
})
export class Header implements OnDestroy {
  readonly conteo: Signal<number>;
  readonly menuAbierto = signal(false);
  readonly menuUsuarioAbierto = signal(false);
  readonly config = inject(ConfiguracionService);
  readonly auth = inject(ClienteAuthService);

  private readonly router = inject(Router);

  constructor(cartService: CartService) {
    this.conteo = cartService.conteo;

    this.router.events
      .pipe(filter((evento) => evento instanceof NavigationEnd))
      .subscribe(() => {
        this.menuAbierto.set(false);
        this.menuUsuarioAbierto.set(false);
      });

    effect(() => {
      const abierto = this.menuAbierto();
      if (abierto) {
        document.addEventListener('pointerdown', this.onDocumentPointerDown);
        document.addEventListener('keydown', this.onDocumentKeyDown);
      } else {
        document.removeEventListener('pointerdown', this.onDocumentPointerDown);
        document.removeEventListener('keydown', this.onDocumentKeyDown);
      }
    });
  }

  alternarMenu(): void {
    this.menuAbierto.set(!this.menuAbierto());
  }

  cerrarMenu(): void {
    this.menuAbierto.set(false);
  }

  private onDocumentPointerDown = (evento: PointerEvent): void => {
    const target = evento.target as Element | null;
    if (!target) {
      return;
    }
    const dentroDelMenu = target.closest('.menu-toggle') || target.closest('.nav');
    if (!dentroDelMenu) {
      evento.preventDefault();
      this.cerrarMenu();
    }
  };

  private onDocumentKeyDown = (evento: KeyboardEvent): void => {
    if (evento.key === 'Escape') {
      evento.preventDefault();
      this.cerrarMenuYEnfocarBoton();
    }
  };

  private cerrarMenuYEnfocarBoton(): void {
    this.cerrarMenu();
    const boton = document.querySelector<HTMLElement>('.menu-toggle');
    boton?.focus();
  }

  ngOnDestroy(): void {
    document.removeEventListener('pointerdown', this.onDocumentPointerDown);
    document.removeEventListener('keydown', this.onDocumentKeyDown);
  }

  alternarUsuario(): void {
    this.menuUsuarioAbierto.set(!this.menuUsuarioAbierto());
  }

  cerrarUsuario(): void {
    this.menuUsuarioAbierto.set(false);
  }

  cerrarSesion(): void {
    this.auth.cerrarSesion();
    this.menuUsuarioAbierto.set(false);
    this.menuAbierto.set(false);
    this.router.navigateByUrl('/');
  }

  @HostListener('window:resize')
  alRedimensionar(): void {
    if (window.innerWidth > 860) {
      this.cerrarMenu();
    }
  }
}
