import { effect, Component, inject, input, signal } from '@angular/core';
import {
  ProductoMasVendido,
  ReporteFiltros,
  VentasPorCategoria,
  VentasPorPeriodoResultado,
  VentasPorTalla,
} from '../../../../core/models/reporte';
import { ReportesService } from '../../../../core/services/reportes.service';
import { ToastService } from '../../../../core/services/toast.service';
import { EmptyStateComponent } from '../../../../shared/components/empty-state/empty-state';
import { BadgeComponent } from '../../../../shared/components/badge/badge';
import { ChartBarsComponent } from '../../../../shared/components/chart-bars/chart-bars';
import { ChartDonutComponent } from '../../../../shared/components/chart-donut/chart-donut';
import { ChartDato } from '../../../../shared/components/chart.models';
import { SpinnerComponent } from '../../../../shared/components/spinner/spinner';

@Component({
  imports: [BadgeComponent, ChartBarsComponent, ChartDonutComponent, EmptyStateComponent, SpinnerComponent],
  selector: 'app-reportes-ventas',
  standalone: true,
  styleUrl: './reportes-ventas.scss',
  templateUrl: './reportes-ventas.html',
})
export class ReportesVentasComponent {
  private readonly reportesService = inject(ReportesService);
  private readonly toast = inject(ToastService);

  readonly filtros = input<ReporteFiltros>({});

  readonly ventasPeriodo = signal<VentasPorPeriodoResultado | null>(null);
  readonly masVendidos = signal<ProductoMasVendido[]>([]);
  readonly porCategoria = signal<VentasPorCategoria[]>([]);
  readonly porTalla = signal<VentasPorTalla[]>([]);
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

    this.reportesService.ventasPorPeriodo(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.ventasPeriodo.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar las ventas por período.'),
    });

    this.reportesService.productosMasVendidos(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.masVendidos.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar los productos más vendidos.'),
    });

    this.reportesService.ventasPorCategoria(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.porCategoria.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar las ventas por categoría.'),
    });

    this.reportesService.ventasPorTalla(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.porTalla.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar las ventas por talla.'),
    });

    this.cargando.set(false);
  }

  chartVentas(): ChartDato[] {
    const datos = this.ventasPeriodo()?.datos ?? [];
    return datos.map((d) => ({
      etiqueta: d.periodo,
      valor: parseFloat(d.monto_total),
    }));
  }

  chartCategorias(): ChartDato[] {
    return this.porCategoria().map((c) => ({
      etiqueta: c.categoria,
      valor: parseFloat(c.monto_total),
      color: this.colorPorIndice(this.porCategoria().indexOf(c)),
    }));
  }

  chartTallas(): ChartDato[] {
    return this.porTalla().map((t) => ({
      etiqueta: t.talla,
      valor: parseFloat(t.monto_total),
      color: this.colorPorIndice(this.porTalla().indexOf(t)),
    }));
  }

  colorPorIndice(indice: number): string {
    const paleta = ['#a13a75', '#c9a227', '#2f6f9f', '#1f8a58', '#b26a00', '#7a5fa0', '#c0392b', '#8a5a44'];
    return paleta[indice % paleta.length];
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  etiquetaAgrupacion(): string {
    const agrupacion = this.ventasPeriodo()?.agrupacion ?? 'mes';
    if (agrupacion === 'dia') {
      return 'día';
    }
    if (agrupacion === 'semana') {
      return 'semana';
    }
    return 'mes';
  }
}