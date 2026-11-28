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
  readonly oculto = signal(false);
  readonly config = inject(ConfiguracionService);
  readonly auth = inject(ClienteAuthService);

  private readonly router = inject(Router);
  private readonly mediaHorizontal = window.matchMedia('(orientation: landscape) and (max-height: 600px)');
  private scrollActivo = false;
  private ultimoScrollY = 0;
  private cuadroScroll = 0;
  private readonly umbralArriba = 8;
  private readonly topeVisible = 40;

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

    this.mediaHorizontal.addEventListener('change', this.onMediaChange);
    this.actualizarEscuchaScroll();
  }

  alternarMenu(): void {
    this.menuAbierto.set(!this.menuAbierto());
    if (this.menuAbierto()) {
      this.oculto.set(false);
    }
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
    this.mediaHorizontal.removeEventListener('change', this.onMediaChange);
    window.removeEventListener('scroll', this.onScroll);
    if (this.cuadroScroll) {
      cancelAnimationFrame(this.cuadroScroll);
    }
  }

  private readonly onMediaChange = (): void => {
    this.actualizarEscuchaScroll();
  };

  private actualizarEscuchaScroll(): void {
    const activo = this.mediaHorizontal.matches;
    if (activo && !this.scrollActivo) {
      window.addEventListener('scroll', this.onScroll, { passive: true });
      this.scrollActivo = true;
    } else if (!activo && this.scrollActivo) {
      window.removeEventListener('scroll', this.onScroll);
      this.scrollActivo = false;
    }
    if (this.cuadroScroll) {
      cancelAnimationFrame(this.cuadroScroll);
      this.cuadroScroll = 0;
    }
    // Al entrar/salir del modo (p. ej. rotar) el encabezado queda visible.
    this.oculto.set(false);
    this.ultimoScrollY = window.scrollY;
  }

  private readonly onScroll = (): void => {
    if (this.cuadroScroll) {
      return;
    }
    this.cuadroScroll = requestAnimationFrame(() => {
      this.cuadroScroll = 0;
      this.evaluarScroll();
    });
  };

  private evaluarScroll(): void {
    const y = window.scrollY;
    if (this.menuAbierto() || this.hayFocoEnCampoDelHeader()) {
      this.oculto.set(false);
      this.ultimoScrollY = y;
      return;
    }
    if (y < this.topeVisible) {
      this.oculto.set(false);
      this.ultimoScrollY = y;
      return;
    }
    const delta = y - this.ultimoScrollY;
    if (Math.abs(delta) < this.umbralArriba) {
      return;
    }
    this.oculto.set(delta > 0); // hacia abajo oculta; hacia arriba muestra
    this.ultimoScrollY = y;
  }

  private hayFocoEnCampoDelHeader(): boolean {
    const activo = document.activeElement as HTMLElement | null;
    if (!activo) {
      return false;
    }
    const esCampoDeTexto =
      activo.tagName === 'INPUT' ||
      activo.tagName === 'TEXTAREA' ||
      activo.tagName === 'SELECT' ||
      activo.isContentEditable;
    if (!esCampoDeTexto) {
      return false;
    }
    return !!(activo.closest('.header') || activo.closest('.buscador'));
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
