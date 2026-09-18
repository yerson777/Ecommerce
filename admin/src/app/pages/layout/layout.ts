import { Component, HostListener, inject, OnInit, signal } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { ToastContainerComponent } from '../../shared/components/toast-container/toast-container';

@Component({
  imports: [RouterOutlet, RouterLink, RouterLinkActive, ToastContainerComponent, NotificationCenterComponent],
  selector: 'app-layout',
  standalone: true,
  templateUrl: './layout.html',
  styleUrl: './layout.scss',
})
export class LayoutComponent implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly usuario = this.auth.usuario;
  readonly menuAbierto = signal(false);

  constructor() {
    this.router.events
      .pipe(filter((evento) => evento instanceof NavigationEnd))
      .subscribe(() => this.menuAbierto.set(false));
  }

  ngOnInit(): void {
    if (!this.usuario()) {
      this.auth.cargarUsuario();
    }
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

  cerrarSesion(): void {
    this.auth.cerrarSesion();
    this.router.navigate(['/login']);
  }

  titulo(): string {
    const url = this.router.url;

    if (url.startsWith('/productos')) {
      return 'Productos';
    }
    if (url.startsWith('/inventario')) {
      return 'Inventario';
    }
    if (url.startsWith('/ventas')) {
      return 'Ventas';
    }
    if (url.startsWith('/pedidos')) {
      return 'Pedidos';
    }
    if (url.startsWith('/clientes')) {
      return 'Clientes';
    }
    if (url.startsWith('/pagos')) {
      return 'Pagos';
    }
    if (url.startsWith('/reportes')) {
      return 'Reportes';
    }
    if (url.startsWith('/banners')) {
      return 'Banners';
    }

    return 'Dashboard';
  }

  inicial(): string {
    return (this.usuario()?.name ?? 'A').charAt(0).toUpperCase();
  }
}