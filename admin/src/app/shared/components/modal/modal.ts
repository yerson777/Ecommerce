import {
  ChangeDetectionStrategy,
  Component,
  HostListener,
  input,
  output,
} from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-modal',
  standalone: true,
  templateUrl: './modal.html',
  styleUrl: './modal.scss',
})
export class ModalComponent {
  readonly title = input<string>('');
  readonly width = input<string>('560px');
  readonly closable = input(true);
  readonly closed = output<void>();

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.closable()) {
      this.closed.emit();
    }
  }

  onBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget && this.closable()) {
      this.closed.emit();
    }
  }
}