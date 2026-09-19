import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { interval } from 'rxjs';
import { DashboardService } from '../../core/services/dashboard.service';
import { DashboardResumen } from '../../core/models/dashboard';
import { ETIQUETA_ESTADO, TONO_ESTADO } from '../../core/models/producto';
import { ETIQUETA_ESTADO_VENTA, TONO_ESTADO_VENTA } from '../../core/models/venta';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { ProgressComponent } from '../../shared/components/progress/progress';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../shared/components/stat-card/stat-card';

@Component({
  imports: [
    StatCardComponent,
    BadgeComponent,
    ProgressComponent,
    EmptyStateComponent,
    SpinnerComponent,
    NotificationCenterComponent,
  ],
  selector: 'app-dashboard',
  standalone: true,
  styleUrl: './dashboard.scss',
  templateUrl: './dashboard.html',
})
export class DashboardComponent implements OnInit {
  private readonly dashboardService = inject(DashboardService);
  private readonly destroyRef = inject(DestroyRef);

  readonly datos = signal<DashboardResumen | null>(null);
  readonly cargando = signal(true);
  error: string | null = null;

  ngOnInit(): void {
    this.cargar();
    this.aplicarRefrescoAutomatico();
  }

  private aplicarRefrescoAutomatico(): void {
    interval(30000)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        if (!this.cargando()) {
          this.cargar();
        }
      });
  }

  cargar(): void {
    this.cargando.set(true);
    this.error = null;

    this.dashboardService.resumen().subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.datos.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.error = err.message ?? 'No se pudo cargar el dashboard.';
      },
    });
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as keyof typeof TONO_ESTADO] ?? 'neutral';
  }

  etiquetaEstadoVenta(estado: string): string {
    return ETIQUETA_ESTADO_VENTA[estado as keyof typeof ETIQUETA_ESTADO_VENTA] ?? estado;
  }

  tonoEstadoVenta(estado: string): string {
    return TONO_ESTADO_VENTA[estado as keyof typeof TONO_ESTADO_VENTA] ?? 'neutral';
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  totalInventario(): number {
    const d = this.datos();
    return d?.inventario.por_estado.reduce((acc, item) => acc + item.total, 0) ?? 0;
  }
}