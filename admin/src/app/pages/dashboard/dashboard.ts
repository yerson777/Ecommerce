import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Component, DestroyRef, computed, inject, OnInit, signal } from '@angular/core';
import { interval } from 'rxjs';
import { DashboardService } from '../../core/services/dashboard.service';
import { ReportesService } from '../../core/services/reportes.service';
import { DashboardResumen, PedidoPorAtender } from '../../core/models/dashboard';
import { ETIQUETA_ESTADO, TONO_ESTADO } from '../../core/models/producto';
import { ETIQUETA_ESTADO_VENTA, TONO_ESTADO_VENTA } from '../../core/models/venta';
import { PeriodoReporte, VentaPorPeriodo } from '../../core/models/reporte';
import { BadgeComponent, BadgeTone } from '../../shared/components/badge/badge';
import { ChartBarsComponent } from '../../shared/components/chart-bars/chart-bars';
import { ChartDonutComponent } from '../../shared/components/chart-donut/chart-donut';
import { ChartDato } from '../../shared/components/chart.models';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import {
  ETIQUETAS_PERIODO,
  PeriodFilterComponent,
} from '../../shared/components/period-filter/period-filter';
import { ProgressComponent } from '../../shared/components/progress/progress';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../shared/components/stat-card/stat-card';

/** Paleta de los donuts, alineada con el tema. */
const PALETA = ['#a13a75', '#c9628f', '#6d4a7c', '#d98ba8', '#8f5a9e', '#b57ab5', '#5c3a6e', '#e0a3bd'];

const TONOS_PAGO: Record<string, BadgeTone> = {
  pagado: 'success',
  parcial: 'warning',
  pendiente: 'danger',
  cancelado: 'neutral',
  reembolsado: 'info',
};

const ETIQUETAS_PAGO: Record<string, string> = {
  pagado: 'Pagado',
  parcial: 'Parcial',
  pendiente: 'Sin pagar',
  cancelado: 'Cancelado',
  reembolsado: 'Reembolsado',
};

@Component({
  imports: [
    StatCardComponent,
    BadgeComponent,
    ProgressComponent,
    EmptyStateComponent,
    SpinnerComponent,
    NotificationCenterComponent,
    PeriodFilterComponent,
    ChartBarsComponent,
    ChartDonutComponent,
  ],
  selector: 'app-dashboard',
  standalone: true,
  styleUrl: './dashboard.scss',
  templateUrl: './dashboard.html',
})
export class DashboardComponent implements OnInit {
  private readonly dashboardService = inject(DashboardService);
  private readonly reportesService = inject(ReportesService);
  private readonly destroyRef = inject(DestroyRef);

  readonly datos = signal<DashboardResumen | null>(null);
  readonly cargando = signal(true);
  error: string | null = null;

  // Filtro de período del gráfico de ventas.
  readonly periodo = signal<PeriodoReporte>('ultimos_30_dias');
  readonly fechaDesde = signal('');
  readonly fechaHasta = signal('');
  readonly cargandoGrafico = signal(false);

  readonly ventasPeriodo = signal<VentaPorPeriodo[]>([]);

  /** Etiqueta del período, taken del propio filtro para no duplicar textos. */
  readonly etiquetaPeriodo = computed(() => ETIQUETAS_PERIODO[this.periodo()]);

  ngOnInit(): void {
    this.cargar();
    this.cargarGrafico();
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

  /** Llamado por app-period-filter. */
  alCambiarPeriodo(): void {
    this.cargarGrafico();
  }

  private cargarGrafico(): void {
    this.cargandoGrafico.set(true);

    const params: Record<string, unknown> = { periodo: this.periodo() };
    if (this.periodo() === 'personalizado') {
      params['fecha_desde'] = this.fechaDesde();
      params['fecha_hasta'] = this.fechaHasta();
    }

    this.reportesService.ventasPorPeriodo(params).subscribe({
      next: (res) => {
        this.cargandoGrafico.set(false);
        this.ventasPeriodo.set(res.data?.datos ?? []);
      },
      error: () => {
        this.cargandoGrafico.set(false);
        this.ventasPeriodo.set([]);
      },
    });
  }

  // ---- Datos derivados para el gráfico ----

  /**
   * El endpoint agrupa por día pero sólo devuelve los días que tuvieron venta,
   * así que una ventana de 30 días puede llegar con 5 barras. Se completa la
   * serie con ceros para que la línea de tiempo sea continua y no engañe.
   */
  readonly serieDiaria = computed<ChartDato[]>(() => {
    const porDia = new Map(this.ventasPeriodo().map((v) => [v.periodo, parseFloat(v.monto_total)]));
    const dias = this.diasDelPeriodo();
    if (dias.length === 0) {
      return [];
    }

    return dias.map((fecha) => ({
      etiqueta: this.etiquetaDia(fecha),
      valor: porDia.get(fecha) ?? 0,
    }));
  });

  private diasDelPeriodo(): string[] {
    const hoy = new Date();
    let dias = 30;

    switch (this.periodo()) {
      case 'hoy':
        dias = 1;
        break;
      case 'ayer':
        dias = 2;
        break;
      case 'ultimos_7_dias':
        dias = 7;
        break;
      case 'ultimos_30_dias':
        dias = 30;
        break;
      case 'este_mes':
        dias = hoy.getDate();
        break;
      default:
        dias = 30;
    }

    const salida: string[] = [];
    for (let i = dias - 1; i >= 0; i--) {
      const d = new Date(hoy);
      d.setDate(hoy.getDate() - i);
      salida.push(d.toISOString().slice(0, 10));
    }
    return salida;
  }

  private etiquetaDia(fecha: string): string {
    const [, mes, dia] = fecha.split('-');
    return `${dia}/${mes}`;
  }

  readonly totalPeriodo = computed(() =>
    this.serieDiaria().reduce((acc, d) => acc + d.valor, 0),
  );

  readonly ventasDelPeriodo = computed(() =>
    this.ventasPeriodo().reduce((acc, v) => acc + v.cantidad_ventas, 0),
  );

  // ---- Datos derivados para los donuts ----

  readonly chartCategorias = computed<ChartDato[]>(() =>
    (this.datos()?.ventas_por_categoria ?? []).map((c, i) => ({
      etiqueta: c.categoria,
      valor: parseFloat(c.monto_total),
      color: PALETA[i % PALETA.length],
    })),
  );

  readonly chartTallas = computed<ChartDato[]>(() =>
    (this.datos()?.ventas_por_talla ?? []).map((t, i) => ({
      etiqueta: t.talla,
      valor: parseFloat(t.monto_total),
      color: PALETA[(i + 3) % PALETA.length],
    })),
  );

  // ---- Utilidades de presentación ----

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

  etiquetaPedido(estado: string): string {
    return this.etiquetaEstado(estado);
  }

  tonoPedido(estado: string): string {
    return this.tonoEstado(estado);
  }

  etiquetaPago(estado: string): string {
    return ETIQUETAS_PAGO[estado] ?? estado;
  }

  tonoPago(estado: string): BadgeTone {
    return TONOS_PAGO[estado] ?? 'neutral';
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  totalInventario(): number {
    const d = this.datos();
    return d?.inventario.por_estado.reduce((acc, item) => acc + item.total, 0) ?? 0;
  }

  /** Filas de "Pedidos por atender" con saldo > 0 primero: lo que urge cobrar. */
  pedidosUrgentes(lista: PedidoPorAtender[]): PedidoPorAtender[] {
    return [...lista].sort((a, b) => parseFloat(b.saldo) - parseFloat(a.saldo));
  }

  tieneSaldo(pedido: PedidoPorAtender): boolean {
    return parseFloat(pedido.saldo) > 0.01;
  }
}
