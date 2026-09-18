import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { ChartDato } from '../chart.models';

const PALETA = [
  '#a13a75',
  '#c9a227',
  '#2f6f9f',
  '#1f8a58',
  '#b26a00',
  '#7a5fa0',
  '#c0392b',
  '#3f7f8f',
  '#8a5a44',
  '#5f7a3a',
];

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-chart-donut',
  standalone: true,
  styleUrl: './chart-donut.scss',
  templateUrl: './chart-donut.html',
})
export class ChartDonutComponent {
  readonly datos = input.required<ChartDato[]>();
  readonly size = input(180);
  readonly esMonto = input(true);

  readonly total = computed(() => this.datos().reduce((acc, d) => acc + d.valor, 0));

  readonly segmentos = computed(() => {
    const items = this.datos();
    const total = this.total();
    const radio = 70;
    const circunferencia = 2 * Math.PI * radio;

    if (items.length === 0 || total <= 0) {
      return [];
    }

    let acumulado = 0;

    return items.map((item, index) => {
      const porcentaje = (item.valor / total) * 100;
      const dash = (porcentaje / 100) * circunferencia;
      acumulado += item.valor;
      const offset = -((acumulado - item.valor / 2) / total) * circunferencia;

      return {
        etiqueta: item.etiqueta,
        valor: item.valor,
        porcentaje,
        color: item.color ?? PALETA[index % PALETA.length],
        dash: dash.toFixed(2),
        gap: circunferencia.toFixed(2),
        offset: offset.toFixed(2),
        radio,
      };
    });
  });

  formato(valor: number): string {
    return this.esMonto() ? `$${valor.toFixed(2)}` : String(valor);
  }

  porcentaje(valor: number): number {
    const total = this.total();
    return total > 0 ? Math.round((valor / total) * 100) : 0;
  }
}