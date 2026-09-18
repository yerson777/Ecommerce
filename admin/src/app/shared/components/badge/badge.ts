import { ChangeDetectionStrategy, Component, input } from '@angular/core';

export type BadgeTone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-badge',
  standalone: true,
  templateUrl: './badge.html',
  styleUrl: './badge.scss',
})
export class BadgeComponent {
  readonly tone = input<string>('neutral');
}