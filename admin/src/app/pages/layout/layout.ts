import { Component, ElementRef, HostListener, inject, OnInit, signal, viewChild } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { ToastContainerComponent } from '../../shared/components/toast-container/toast-container';
import { AuthService } from '../../core/services/auth.service';
import { ConfiguracionService } from '../../core/services/configuracion.service';

@Component({
  imports: [RouterOutlet, RouterLink, RouterLinkActive, ToastContainerComponent],
  selector: 'app-layout',
  standalone: true,
  templateUrl: './layout.html',
  styleUrl: './layout.scss',
})
export class LayoutComponent implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly config = inject(ConfiguracionService);

  private readonly sidebar = viewChild<ElementRef<HTMLElement>>('sidebar');

  readonly usuario = this.auth.usuario;
  readonly menuAbierto = signal(false);
  readonly cerrando = signal(false);
  readonly gruposAbiertos = signal<Record<string, boolean>>({ general: false, ventas: false, envios: false, catalogo: false, compras: false, caja: false, sistema: false });
  readonly grupoAbierto = signal<string | null>(null);
  readonly logoUrl = signal<string | null>(null);

  private readonly rutasGrupo: Record<string, string[]> = {
    general: ['/dashboard', '/reportes'],
    ventas: ['/ventas', '/reservas', '/devoluciones', '/pedidos', '/clientes', '/cupones'],
    envios: ['/envios'],
    catalogo: ['/productos', '/inventario', '/categorias', '/tallas-marcas', '/banners', '/ofertas'],
    compras: ['/compras', '/proveedores'],
    caja: ['/pagos', '/caja'],
    sistema: ['/configuracion', '/usuarios'],
  };

  constructor() {
    this.router.events
      .pipe(filter((evento) => evento instanceof NavigationEnd))
      .subscribe((evento) => {
        this.menuAbierto.set(false);
      });
    this.cargarLogo();
  }

  private cargarLogo(): void {
    this.config.logoTienda().subscribe({
      next: (res) => {
        this.logoUrl.set(res.data?.logo_url ?? null);
      },
      error: () => {
        this.logoUrl.set(null);
      },
    });
  }

  ngOnInit(): void {
    if (!this.usuario()) {
      this.auth.cargarUsuario();
    }
  }

  private actualizarGrupoPorRuta(url: string): void {
    // En móvil (<768px) no expandir ningún grupo automáticamente al cargar.
    // Los ítems activos siguen resaltados por routerLinkActive en el template.
    if (window.innerWidth <= 767) {
      const estadoVacio: Record<string, boolean> = {};
      for (const g of Object.keys(this.rutasGrupo)) {
        estadoVacio[g] = false;
      }
      this.gruposAbiertos.set(estadoVacio);
      this.grupoAbierto.set(null);
      return;
    }

    const ruta = url.split('?')[0].split('#')[0];
    for (const [grupo, rutas] of Object.entries(this.rutasGrupo)) {
      if (rutas.some((r) => ruta === r || ruta.startsWith(r + '/'))) {
        this.grupoAbierto.set(grupo);
        this.gruposAbiertos.set({ ...Object.fromEntries(Object.keys(this.rutasGrupo).map((g) => [g, g === grupo])), ...({} as Record<string, boolean>) });
        // asegurar estado acordeón
        const estado: Record<string, boolean> = {};
        for (const g of Object.keys(this.rutasGrupo)) {
          estado[g] = g === grupo;
        }
        this.gruposAbiertos.set(estado);
        return;
      }
    }
    // si no coincide con ningún grupo, cerrar todos
    const estadoVacio: Record<string, boolean> = {};
    for (const g of Object.keys(this.rutasGrupo)) {
      estadoVacio[g] = false;
    }
    this.gruposAbiertos.set(estadoVacio);
    this.grupoAbierto.set(null);
  }

  toggleGrupo(grupo: string): void {
    this.quitarFocoDelMenu();
    if (this.grupoAbierto() === grupo) {
      this.grupoAbierto.set(null);
      this.gruposAbiertos.update((estado) => ({ ...estado, [grupo]: false }));
      return;
    }
    this.grupoAbierto.set(grupo);
    this.gruposAbiertos.update((estado) => {
      const nuevo: Record<string, boolean> = {};
      for (const clave of Object.keys(estado)) {
        nuevo[clave] = clave === grupo;
      }
      return nuevo;
    });
  }



  /**
   * Quita el foco del elemento clicado dentro del menú. Tras hacer clic con
   * el mouse el enlace/botón conserva el foco y el `:focus-within` mantenía
   * el sidebar "medio expandido" (submenú abierto, textos ocultos) aunque el
   * mouse ya no estuviera encima. Se conserva el foco cuando viene del
   * teclado (:focus-visible) para no romper la navegación con Tab.
   */
  private quitarFocoDelMenu(): void {
    const aside = this.sidebar()?.nativeElement;
    const activo = document.activeElement;
    if (
      aside &&
      activo instanceof HTMLElement &&
      aside.contains(activo) &&
      !activo.matches(':focus-visible')
    ) {
      activo.blur();
    }
  }

  cerrarMenu(): void {
    this.quitarFocoDelMenu();
    this.menuAbierto.set(false);
  }

  alternarMenu(): void {
    this.menuAbierto.set(!this.menuAbierto());
  }

  @HostListener('window:resize')
  alRedimensionar(): void {
    if (window.innerWidth > 768) {
      this.cerrarMenu();
    }
  }

  cerrarSesion(): void {
    if (this.cerrando()) {
      return;
    }
    this.cerrando.set(true);
    this.auth.cerrarSesion();
    void this.router.navigate(['/login']).finally(() => this.cerrando.set(false));
  }

  inicial(): string {
    return (this.usuario()?.name ?? 'A').charAt(0).toUpperCase();
  }
}