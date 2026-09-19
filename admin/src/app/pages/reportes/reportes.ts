import { Component, computed, signal } from '@angular/core';
import { PeriodoReporte, ReporteFiltros } from '../../core/models/reporte';
import { PeriodFilterComponent } from '../../shared/components/period-filter/period-filter';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { ReportesClientesComponent } from './tabs/reportes-clientes/reportes-clientes';
import { ReportesInventarioComponent } from './tabs/reportes-inventario/reportes-inventario';
import { ReportesPagosComponent } from './tabs/reportes-pagos/reportes-pagos';
import { ReportesPedidosComponent } from './tabs/reportes-pedidos/reportes-pedidos';
import { ReportesResumenComponent } from './tabs/reportes-resumen/reportes-resumen';
import { ReportesVentasComponent } from './tabs/reportes-ventas/reportes-ventas';

export type TabReporte = 'resumen' | 'ventas' | 'pedidos' | 'pagos' | 'inventario' | 'clientes';

@Component({
  imports: [
    PeriodFilterComponent,
    NotificationCenterComponent,
    ReportesResumenComponent,
    ReportesVentasComponent,
    ReportesPedidosComponent,
    ReportesPagosComponent,
    ReportesInventarioComponent,
    ReportesClientesComponent,
  ],
  selector: 'app-reportes',
  standalone: true,
  styleUrl: './reportes.scss',
  templateUrl: './reportes.html',
})
export class ReportesComponent {
  readonly tab = signal<TabReporte>('resumen');

  readonly periodo = signal<PeriodoReporte>('ultimos_30_dias');
  readonly fechaDesde = signal('');
  readonly fechaHasta = signal('');

  readonly filtros = computed<ReporteFiltros>(() => ({
    periodo: this.periodo(),
    fecha_desde: this.fechaDesde() || undefined,
    fecha_hasta: this.fechaHasta() || undefined,
  }));

  readonly tabs: { clave: TabReporte; etiqueta: string; icono: string }[] = [
    { clave: 'resumen', etiqueta: 'Resumen', icono: '◧' },
    { clave: 'ventas', etiqueta: 'Ventas', icono: '§' },
    { clave: 'pedidos', etiqueta: 'Pedidos', icono: '📦' },
    { clave: 'pagos', etiqueta: 'Pagos', icono: '💳' },
    { clave: 'inventario', etiqueta: 'Inventario', icono: '▤' },
    { clave: 'clientes', etiqueta: 'Clientes', icono: '◈' },
  ];

  seleccionarTab(clave: TabReporte): void {
    this.tab.set(clave);
  }

  onPeriodoChange(valor: PeriodoReporte): void {
    this.periodo.set(valor);
  }

  onFechasChange(fechaDesde: string, fechaHasta: string): void {
    this.fechaDesde.set(fechaDesde);
    this.fechaHasta.set(fechaHasta);
  }
}