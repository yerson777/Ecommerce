import { Component, HostListener, inject, OnInit, signal } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { ToastContainerComponent } from '../../shared/components/toast-container/toast-container';
import { AuthService } from '../../core/services/auth.service';
import { Rol } from '../../core/models/usuario';
import { puedeVerModulo } from '../../core/models/usuario';

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

  readonly usuario = this.auth.usuario;
  readonly menuAbierto = signal(false);
  readonly gruposAbiertos = signal<Record<string, boolean>>({ general: false, ventas: false, catalogo: false, caja: false, sistema: false });

  toggleGrupo(grupo: string): void {
    this.gruposAbiertos.update((estado) => ({ ...estado, [grupo]: !estado[grupo] }));
  }

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

  cerrarMenu(): void {
    this.menuAbierto.set(false);
  }

  alternarMenu(): void {
    this.menuAbierto.set(!this.menuAbierto());
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

  inicial(): string {
    return (this.usuario()?.name ?? 'A').charAt(0).toUpperCase();
  }
}