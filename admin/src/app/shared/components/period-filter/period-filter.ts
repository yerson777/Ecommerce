import { Component, input, model, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { PeriodoReporte } from '../../../core/models/reporte';

export const ETIQUETAS_PERIODO: Record<PeriodoReporte, string> = {
  hoy: 'Hoy',
  ayer: 'Ayer',
  ultimos_7_dias: 'Últimos 7 días',
  ultimos_30_dias: 'Últimos 30 días',
  este_mes: 'Este mes',
  mes_anterior: 'Mes anterior',
  este_anio: 'Este año',
  personalizado: 'Rango personalizado',
};

@Component({
  imports: [FormsModule],
  selector: 'app-period-filter',
  standalone: true,
  styleUrl: './period-filter.scss',
  templateUrl: './period-filter.html',
})
export class PeriodFilterComponent {
  readonly periodo = model.required<PeriodoReporte>();
  readonly fechaDesde = model('');
  readonly fechaHasta = model('');
  readonly cargando = input(false);
  readonly cambio = output<void>();

  readonly periodos = Object.entries(ETIQUETAS_PERIODO) as [PeriodoReporte, string][];

  esPersonalizado(): boolean {
    return this.periodo() === 'personalizado';
  }

  aplicar(): void {
    this.cambio.emit();
  }
}