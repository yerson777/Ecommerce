import { effect, Component, inject, input, signal } from '@angular/core';
import {
  ComparacionPeriodo,
  ProductoMasVendido,
  ReporteFiltros,
  TiempoPromedioVenta,
  VentasPorCategoria,
  VentasPorMarca,
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
  readonly porMarca = signal<VentasPorMarca[]>([]);
  readonly tiempoVenta = signal<TiempoPromedioVenta | null>(null);
  readonly comparacion = signal<ComparacionPeriodo | null>(null);
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

    this.reportesService.ventasPorMarca(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.porMarca.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar las ventas por marca.'),
    });

    this.reportesService.tiempoPromedioVenta(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.tiempoVenta.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudo cargar el tiempo promedio de venta.'),
    });

    this.reportesService.comparacion(f).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.comparacion.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudo cargar la comparación con el período anterior.'),
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

  chartMarcas(): ChartDato[] {
    return this.porMarca().map((m) => ({
      etiqueta: m.marca,
      valor: parseFloat(m.monto_total),
      color: this.colorPorIndice(this.porMarca().indexOf(m)),
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

  dias(valor: number | null | undefined): string {
    if (valor === null || valor === undefined) {
      return '—';
    }
    return valor === 1 ? '1 día' : `${valor} días`;
  }

  variacion(valor: number | null | undefined): string {
    if (valor === null || valor === undefined) {
      return '—';
    }
    const signo = valor > 0 ? '+' : '';
    return `${signo}${valor.toFixed(1)}%`;
  }

  variacionTone(valor: number | null | undefined): 'success' | 'danger' | 'info' {
    if (valor === null || valor === undefined || valor === 0) {
      return 'info';
    }
    return valor > 0 ? 'success' : 'danger';
  }

  exportar(): void {
    const f = this.filtros();

    this.reportesService
      .exportar('ventas', {
        periodo: f.periodo,
        fecha_desde: f.fecha_desde,
        fecha_hasta: f.fecha_hasta,
      })
      .subscribe({
        next: (blob) => this.descargarBlob(blob, 'reporte_ventas.csv'),
        error: (err) => this.toast.error(err.message ?? 'No se pudo exportar el reporte de ventas.'),
      });
  }

  imprimir(): void {
    window.print();
  }

  rangoTexto(): string {
    const actual = this.comparacion()?.rango_actual;
    return actual ? `${actual.desde} a ${actual.hasta}` : 'Todo el histórico';
  }

  hoyTexto(): string {
    const d = new Date();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
  }

  private descargarBlob(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
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