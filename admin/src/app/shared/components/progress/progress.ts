import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-progress',
  standalone: true,
  templateUrl: './progress.html',
  styleUrl: './progress.scss',
})
export class ProgressComponent {
  readonly label = input.required<string>();
  readonly value = input(0);
  readonly total = input(1);
  readonly tone = input<string>('primary');

  porcentaje(): number {
    if (this.total() <= 0) {
      return 0;
    }
    return Math.min(100, Math.round((this.value() / this.total()) * 100));
  }
}