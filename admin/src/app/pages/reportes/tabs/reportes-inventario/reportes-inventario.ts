import { Component, inject, input, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CategoriaRef, EstadoPrenda, ETIQUETA_ESTADO, TONO_ESTADO } from '../../../../core/models/producto';
import { ConteoEstado, ConteoNombre, ReporteFiltros } from '../../../../core/models/reporte';
import { CatalogosService } from '../../../../core/services/catalogos.service';
import { ReportesService } from '../../../../core/services/reportes.service';
import { ToastService } from '../../../../core/services/toast.service';
import { ChartDonutComponent } from '../../../../shared/components/chart-donut/chart-donut';
import { ChartDato } from '../../../../shared/components/chart.models';
import { EmptyStateComponent } from '../../../../shared/components/empty-state/empty-state';
import { SpinnerComponent } from '../../../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../../../shared/components/stat-card/stat-card';

@Component({
  imports: [FormsModule, ChartDonutComponent, EmptyStateComponent, SpinnerComponent, StatCardComponent],
  selector: 'app-reportes-inventario',
  standalone: true,
  styleUrl: './reportes-inventario.scss',
  templateUrl: './reportes-inventario.html',
})
export class ReportesInventarioComponent implements OnInit {
  private readonly reportesService = inject(ReportesService);
  private readonly catalogosService = inject(CatalogosService);
  private readonly toast = inject(ToastService);

  readonly filtros = input<ReporteFiltros>({});

  readonly total = signal(0);
  readonly disponibles = signal(0);
  readonly reservadas = signal(0);
  readonly vendidas = signal(0);
  readonly porCategoria = signal<ConteoNombre[]>([]);
  readonly porTalla = signal<ConteoNombre[]>([]);
  readonly porEstado = signal<ConteoEstado[]>([]);
  readonly cargando = signal(true);
  error: string | null = null;

  readonly categorias = signal<CategoriaRef[]>([]);
  readonly tallas = signal<{ id: number; nombre: string }[]>([]);
  readonly categoriaSeleccionada = signal<number | null>(null);
  readonly tallaSeleccionada = signal<number | null>(null);
  readonly estadoSeleccionado = signal<'' | EstadoPrenda>('');

  readonly estados: { valor: EstadoPrenda; etiqueta: string }[] = [
    { valor: 'disponible', etiqueta: 'Disponible' },
    { valor: 'reservada', etiqueta: 'Reservada' },
    { valor: 'vendida', etiqueta: 'Vendida' },
  ];

  ngOnInit(): void {
    this.catalogosService.categorias().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.categorias.set(res.data);
        }
      },
      error: () => undefined,
    });

    this.catalogosService.tallas().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.tallas.set(res.data);
        }
      },
      error: () => undefined,
    });

    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.error = null;

    this.reportesService
      .inventario({
        categoria_id: this.categoriaSeleccionada() ?? undefined,
        talla_id: this.tallaSeleccionada() ?? undefined,
        estado: this.estadoSeleccionado() || undefined,
      })
      .subscribe({
        next: (res) => {
          this.cargando.set(false);
          if (res.success && res.data) {
            const d = res.data;
            this.total.set(d.total);
            this.disponibles.set(d.disponibles);
            this.reservadas.set(d.reservadas);
            this.vendidas.set(d.vendidas);
            this.porCategoria.set(d.porCategoria);
            this.porTalla.set(d.porTalla);
            this.porEstado.set(d.porEstado);
          }
        },
        error: (err) => {
          this.cargando.set(false);
          this.error = err.message ?? 'No se pudo cargar el inventario.';
        },
      });
  }

  limpiarFiltros(): void {
    this.categoriaSeleccionada.set(null);
    this.tallaSeleccionada.set(null);
    this.estadoSeleccionado.set('');
    this.cargar();
  }

  chartEstado(): ChartDato[] {
    const paleta: Record<string, string> = {
      disponible: '#1f8a58',
      reservada: '#b26a00',
      vendida: '#c0392b',
    };
    return this.porEstado().map((e) => ({
      etiqueta: ETIQUETA_ESTADO[e.estado as EstadoPrenda] ?? e.estado,
      valor: e.total,
      color: paleta[e.estado] ?? '#a13a75',
    }));
  }

  chartCategoria(): ChartDato[] {
    const paleta = ['#a13a75', '#c9a227', '#2f6f9f', '#1f8a58', '#b26a00', '#7a5fa0', '#c0392b', '#8a5a44'];
    return this.porCategoria().map((c, index) => ({
      etiqueta: c.nombre,
      valor: c.total,
      color: paleta[index % paleta.length],
    }));
  }

  chartTalla(): ChartDato[] {
    const paleta = ['#a13a75', '#c9a227', '#2f6f9f', '#1f8a58', '#b26a00', '#7a5fa0', '#c0392b', '#8a5a44'];
    return this.porTalla().map((t, index) => ({
      etiqueta: t.nombre,
      valor: t.total,
      color: paleta[index % paleta.length],
    }));
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO[estado as EstadoPrenda] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as EstadoPrenda] ?? 'neutral';
  }
}