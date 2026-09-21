import { effect, Component, inject, input, signal } from '@angular/core';
import { ClientesReporte, ReporteFiltros } from '../../../../core/models/reporte';
import { ReportesService } from '../../../../core/services/reportes.service';
import { ToastService } from '../../../../core/services/toast.service';
import { EmptyStateComponent } from '../../../../shared/components/empty-state/empty-state';
import { SpinnerComponent } from '../../../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../../../shared/components/stat-card/stat-card';

@Component({
  imports: [StatCardComponent, EmptyStateComponent, SpinnerComponent],
  selector: 'app-reportes-clientes',
  standalone: true,
  styleUrl: './reportes-clientes.scss',
  templateUrl: './reportes-clientes.html',
})
export class ReportesClientesComponent {
  private readonly reportesService = inject(ReportesService);
  private readonly toast = inject(ToastService);

  readonly filtros = input<ReporteFiltros>({});

  readonly datos = signal<ClientesReporte | null>(null);
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

    this.reportesService.clientes(f).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.datos.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.error = err.message ?? 'No se pudieron cargar los clientes.';
      },
    });
  }

  moneda(valor: string | null | undefined): string {
    const numero = parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}