import { effect, Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  ETIQUETA_ESTADO_PAGO_PEDIDO,
  ETIQUETA_ESTADO_PEDIDO,
  EstadoPagoPedido,
  EstadoPedido,
  TONO_ESTADO_PAGO_PEDIDO,
  TONO_ESTADO_PEDIDO,
} from '../../../../core/models/pedido';
import { PedidoReporte, ReporteFiltros } from '../../../../core/models/reporte';
import { ReportesService } from '../../../../core/services/reportes.service';
import { ToastService } from '../../../../core/services/toast.service';
import { BadgeComponent } from '../../../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../../../shared/components/empty-state/empty-state';
import { PaginatorComponent } from '../../../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../../../shared/components/spinner/spinner';

@Component({
  imports: [FormsModule, BadgeComponent, EmptyStateComponent, PaginatorComponent, SpinnerComponent],
  selector: 'app-reportes-pedidos',
  standalone: true,
  styleUrl: './reportes-pedidos.scss',
  templateUrl: './reportes-pedidos.html',
})
export class ReportesPedidosComponent {
  private readonly reportesService = inject(ReportesService);
  private readonly toast = inject(ToastService);

  readonly filtros = input<ReporteFiltros>({});

  readonly items = signal<PedidoReporte[]>([]);
  readonly total = signal(0);
  readonly pagina = signal(1);
  readonly porPagina = signal(15);
  readonly cargando = signal(true);
  error: string | null = null;

  readonly estadoPedido = signal<'' | EstadoPedido>('');
  readonly estadoPago = signal<'' | EstadoPagoPedido>('');
  readonly busqueda = signal('');

  readonly estadosPedido: { valor: EstadoPedido; etiqueta: string }[] = [
    { valor: 'pendiente', etiqueta: 'Pendiente' },
    { valor: 'confirmado', etiqueta: 'Confirmado' },
    { valor: 'cancelado', etiqueta: 'Cancelado' },
    { valor: 'completado', etiqueta: 'Completado' },
  ];

  readonly estadosPago: { valor: EstadoPagoPedido; etiqueta: string }[] = [
    { valor: 'pendiente', etiqueta: 'Pendiente' },
    { valor: 'parcial', etiqueta: 'Parcial' },
    { valor: 'pagado', etiqueta: 'Pagado' },
  ];

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
      .pedidos({
        periodo: f.periodo,
        fecha_desde: f.fecha_desde,
        fecha_hasta: f.fecha_hasta,
        estado_pedido: this.estadoPedido() || null,
        estado_pago: this.estadoPago() || null,
        cliente: this.busqueda() || null,
        page: this.pagina(),
        per_page: this.porPagina(),
      })
      .subscribe({
        next: (res) => {
          this.cargando.set(false);
          if (res.success && res.data) {
            this.items.set(res.data.items);
            this.total.set(res.data.meta.total);
          }
        },
        error: (err) => {
          this.cargando.set(false);
          this.error = err.message ?? 'No se pudieron cargar los pedidos.';
        },
      });
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.estadoPedido.set('');
    this.estadoPago.set('');
    this.busqueda.set('');
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  ultimaPagina(): number {
    return Math.max(1, Math.ceil(this.total() / this.porPagina()));
  }

  exportar(): void {
    const f = this.filtros();

    this.reportesService
      .exportar('pedidos', {
        periodo: f.periodo,
        fecha_desde: f.fecha_desde,
        fecha_hasta: f.fecha_hasta,
        estado: this.estadoPedido() || null,
      })
      .subscribe({
        next: (blob) => this.descargarBlob(blob, 'reporte_pedidos.csv'),
        error: (err) => this.toast.error(err.message ?? 'No se pudo exportar el reporte.'),
      });
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

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO_PEDIDO[estado as keyof typeof ETIQUETA_ESTADO_PEDIDO] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO_PEDIDO[estado as keyof typeof TONO_ESTADO_PEDIDO] ?? 'neutral';
  }

  etiquetaEstadoPago(estado: string): string {
    return ETIQUETA_ESTADO_PAGO_PEDIDO[estado as keyof typeof ETIQUETA_ESTADO_PAGO_PEDIDO] ?? estado;
  }

  tonoEstadoPago(estado: string): string {
    return TONO_ESTADO_PAGO_PEDIDO[estado as keyof typeof TONO_ESTADO_PAGO_PEDIDO] ?? 'neutral';
  }

  moneda(valor: string): string {
    const numero = parseFloat(valor);
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}