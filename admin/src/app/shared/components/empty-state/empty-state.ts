import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-empty-state',
  standalone: true,
  templateUrl: './empty-state.html',
  styleUrl: './empty-state.scss',
})
export class EmptyStateComponent {
  readonly icon = input('🗂');
  readonly title = input('Sin resultados');
  readonly message = input('');
}