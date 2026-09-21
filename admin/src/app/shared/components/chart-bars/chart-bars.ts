import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { ChartDato } from '../chart.models';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-chart-bars',
  standalone: true,
  styleUrl: './chart-bars.scss',
  templateUrl: './chart-bars.html',
})
export class ChartBarsComponent {
  readonly datos = input.required<ChartDato[]>();
  readonly formatoValor = input<'monto' | 'numero'>('monto');
  readonly altura = input(180);
  readonly colorBase = input('#a13a75');

  readonly maximo = computed(() => {
    const valores = this.datos().map((d) => d.valor);
    if (valores.length === 0) {
      return 1;
    }
    return Math.max(...valores);
  });

  formato(valor: number): string {
    if (this.formatoValor() === 'monto') {
      return `Bs ${valor.toFixed(2)}`;
    }
    return String(valor);
  }
}