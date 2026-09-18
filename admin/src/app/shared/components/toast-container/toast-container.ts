import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { ToastService } from '../../../core/services/toast.service';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-toast-container',
  standalone: true,
  templateUrl: './toast-container.html',
  styleUrl: './toast-container.scss',
})
export class ToastContainerComponent {
  private readonly toastService = inject(ToastService);

  readonly toasts = this.toastService.toasts;

  quitar(id: number): void {
    this.toastService.quitar(id);
  }
}