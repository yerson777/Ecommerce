import { effect, Component, inject, input, signal } from '@angular/core';
import { ReporteFiltros, ReporteResumen } from '../../../../core/models/reporte';
import { ReportesService } from '../../../../core/services/reportes.service';
import { ToastService } from '../../../../core/services/toast.service';
import { EmptyStateComponent } from '../../../../shared/components/empty-state/empty-state';
import { SpinnerComponent } from '../../../../shared/components/spinner/spinner';

@Component({
  imports: [EmptyStateComponent, SpinnerComponent],
  selector: 'app-reportes-resumen',
  standalone: true,
  styleUrl: './reportes-resumen.scss',
  templateUrl: './reportes-resumen.html',
})
export class ReportesResumenComponent {
  private readonly reportesService = inject(ReportesService);
  private readonly toast = inject(ToastService);

  readonly filtros = input<ReporteFiltros>({});

  readonly datos = signal<ReporteResumen | null>(null);
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

    this.reportesService.resumen(f).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.datos.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.error = err.message ?? 'No se pudo cargar el resumen.';
      },
    });
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}