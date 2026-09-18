import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-paginator',
  standalone: true,
  templateUrl: './paginator.html',
  styleUrl: './paginator.scss',
})
export class PaginatorComponent {
  readonly page = input(1);
  readonly lastPage = input(1);
  readonly total = input(0);
  readonly perPage = input(15);

  readonly pageChange = output<number>();

  desde(): number {
    return this.total() === 0 ? 0 : (this.page() - 1) * this.perPage() + 1;
  }

  hasta(): number {
    return Math.min(this.page() * this.perPage(), this.total());
  }

  irA(pagina: number): void {
    const destino = Math.min(Math.max(pagina, 1), this.lastPage());
    if (destino !== this.page()) {
      this.pageChange.emit(destino);
    }
  }
}