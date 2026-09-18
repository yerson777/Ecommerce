import { Component, inject, OnInit, signal } from '@angular/core';
import { InventarioResumen } from '../../core/models/dashboard';
import { ETIQUETA_ESTADO, TONO_ESTADO } from '../../core/models/producto';
import { InventarioService } from '../../core/services/inventario.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ProgressComponent } from '../../shared/components/progress/progress';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../shared/components/stat-card/stat-card';

@Component({
  imports: [StatCardComponent, BadgeComponent, ProgressComponent, EmptyStateComponent, SpinnerComponent],
  selector: 'app-inventario',
  standalone: true,
  styleUrl: './inventario.scss',
  templateUrl: './inventario.html',
})
export class InventarioComponent implements OnInit {
  private readonly inventarioService = inject(InventarioService);

  readonly datos = signal<InventarioResumen | null>(null);
  readonly cargando = signal(true);
  error: string | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.error = null;

    this.inventarioService.resumen().subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.datos.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.error = err.message ?? 'No se pudo cargar el inventario.';
      },
    });
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as keyof typeof TONO_ESTADO] ?? 'neutral';
  }

  totalPorEstado(estado: string): number {
    return this.datos()?.por_estado.find((item) => item.estado === estado)?.total ?? 0;
  }

  etiquetaRango(rango: string): string {
    const mapa: Record<string, string> = {
      menor_100: 'Menos de $100',
      '100_199': '$100 – $199.99',
      '200_499': '$200 – $499.99',
      '500_mas': '$500 o más',
    };

    return mapa[rango] ?? rango;
  }

  suma(lista: { total: number }[]): number {
    return lista.reduce((acc, item) => acc + item.total, 0);
  }
}