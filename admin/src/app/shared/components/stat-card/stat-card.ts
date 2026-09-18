import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-stat-card',
  standalone: true,
  templateUrl: './stat-card.html',
  styleUrl: './stat-card.scss',
})
export class StatCardComponent {
  readonly label = input.required<string>();
  readonly value = input.required<string | number>();
  readonly hint = input<string>('');
  readonly icon = input<string>('');
  readonly tone = input<'primary' | 'success' | 'warning' | 'danger' | 'info' | 'neutral'>('primary');
}