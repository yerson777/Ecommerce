import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-spinner',
  standalone: true,
  template: `<span class="spinner" [style.width.px]="size()" [style.height.px]="size()"></span>`,
  styleUrl: './spinner.scss',
})
export class SpinnerComponent {
  readonly size = input(18);
}