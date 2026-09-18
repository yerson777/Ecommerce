import { effect, Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ETIQUETA_ESTADO_PAGO, TONO_ESTADO_PAGO } from '../../../../core/models/pago';
import {
  MetodoPagoRef,
  PagosPorMetodo,
  PagosReporte,
  ReporteFiltros,
} from '../../../../core/models/reporte';
import { ReportesService } from '../../../../core/services/reportes.service';
import { ToastService } from '../../../../core/services/toast.service';
import { BadgeComponent } from '../../../../shared/components/badge/badge';
import { ChartDonutComponent } from '../../../../shared/components/chart-donut/chart-donut';
import { ChartDato } from '../../../../shared/components/chart.models';
import { EmptyStateComponent } from '../../../../shared/components/empty-state/empty-state';
import { SpinnerComponent } from '../../../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../../../shared/components/stat-card/stat-card';

@Component({
  imports: [FormsModule, BadgeComponent, ChartDonutComponent, EmptyStateComponent, SpinnerComponent, StatCardComponent],
  selector: 'app-reportes-pagos',
  standalone: true,
  styleUrl: './reportes-pagos.scss',
  templateUrl: './reportes-pagos.html',
})
export class ReportesPagosComponent {
  private readonly reportesService = inject(ReportesService);
  private readonly toast = inject(ToastService);

  readonly filtros = input<ReporteFiltros>({});

  readonly datos = signal<PagosReporte | null>(null);
  readonly porMetodo = signal<PagosPorMetodo[]>([]);
  readonly metodos = signal<MetodoPagoRef[]>([]);
  readonly metodoSeleccionado = signal<number | null>(null);
  readonly cargando = signal(true);
  error: string | null = null;

  constructor() {
    effect(() => {
      void this.filtros();
      this.cargar();
    });
  }

  cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);
    this.error = null;

    this.reportesService
      .pagos({
        periodo: f.periodo,
        fecha_desde: f.fecha_desde,
        fecha_hasta: f.fecha_hasta,
        metodo_pago_id: this.metodoSeleccionado() ?? undefined,
      })
      .subscribe({
        next: (res) => {
          this.cargando.set(false);
          if (res.success && res.data) {
            this.datos.set(res.data);
          }
        },
        error: (err) => {
          this.cargando.set(false);
          this.error = err.message ?? 'No se pudieron cargar los pagos.';
        },
      });

    this.reportesService
      .pagosPorMetodo({ periodo: f.periodo, fecha_desde: f.fecha_desde, fecha_hasta: f.fecha_hasta })
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.porMetodo.set(res.data);
          }
        },
        error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar los métodos de pago.'),
      });

    if (this.metodos().length === 0) {
      this.reportesService.metodosPago().subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.metodos.set(res.data);
          }
        },
        error: () => undefined,
      });
    }
  }

  onMetodoChange(): void {
    this.cargar();
  }

  chartMetodos(): ChartDato[] {
    const paleta = ['#a13a75', '#c9a227', '#2f6f9f', '#1f8a58', '#b26a00', '#7a5fa0'];
    return this.porMetodo().map((m, index) => ({
      etiqueta: m.metodo,
      valor: parseFloat(m.total_monto),
      color: paleta[index % paleta.length],
    }));
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO_PAGO[estado as keyof typeof ETIQUETA_ESTADO_PAGO] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO_PAGO[estado as keyof typeof TONO_ESTADO_PAGO] ?? 'neutral';
  }
}