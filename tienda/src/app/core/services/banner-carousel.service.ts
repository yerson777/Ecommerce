import { Injectable, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { BannerPublico } from '../models/banner';
import { CatalogoService } from './catalogo.service';

const INTERVALO_MS = 5000;

@Injectable()
export class BannerCarouselService {
  private readonly catalogo = inject(CatalogoService);
  private readonly router = inject(Router);

  readonly banners = signal<BannerPublico[]>([]);
  readonly activo = signal(0);

  private autoplay?: ReturnType<typeof setInterval>;
  private autoplayPermitido = true;

  cargar(opciones: { autoplayPermitido?: boolean } = {}): void {
    this.autoplayPermitido = opciones.autoplayPermitido ?? true;
    this.catalogo.banners().subscribe({
      next: (res) => {
        this.banners.set(res.data ?? []);
        this.activo.set(0);
        if (this.autoplayPermitido) {
          this.iniciarAutoplay();
        }
      },
    });
  }

  ir(indice: number): void {
    const total = this.banners().length;
    if (total === 0) {
      return;
    }
    this.activo.set(((indice % total) + total) % total);
    this.iniciarAutoplay();
  }

  anterior(): void {
    this.ir(this.activo() - 1);
  }

  siguiente(): void {
    this.ir(this.activo() + 1);
  }

  abrir(banner: BannerPublico): void {
    if (!banner.enlace) {
      return;
    }
    if (banner.enlace.startsWith('/')) {
      this.router.navigateByUrl(banner.enlace);
      return;
    }
    window.open(banner.enlace, '_blank', 'noopener');
  }

  destruir(): void {
    this.detenerAutoplay();
  }

  private avanzar(): void {
    const total = this.banners().length;
    if (total < 2) {
      return;
    }
    this.activo.set((this.activo() + 1) % total);
  }

  private iniciarAutoplay(): void {
    this.detenerAutoplay();
    if (this.banners().length < 2) {
      return;
    }
    this.autoplay = setInterval(() => this.avanzar(), INTERVALO_MS);
  }

  private detenerAutoplay(): void {
    if (this.autoplay) {
      clearInterval(this.autoplay);
      this.autoplay = undefined;
    }
  }
}
