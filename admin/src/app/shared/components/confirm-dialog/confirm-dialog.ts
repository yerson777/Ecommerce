import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { ModalComponent } from '../modal/modal';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [ModalComponent],
  templateUrl: './confirm-dialog.html',
  styleUrl: './confirm-dialog.scss',
})
export class ConfirmDialogComponent {
  readonly title = input('Confirmar acción');
  readonly message = input('');
  readonly confirmLabel = input('Confirmar');
  readonly cancelLabel = input('Cancelar');
  readonly tone = input<'danger' | 'primary'>('danger');

  readonly confirm = output<void>();
  readonly cancel = output<void>();

  onConfirm(): void {
    this.confirm.emit();
  }
}