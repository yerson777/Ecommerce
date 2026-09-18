import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ETIQUETA_ESTADO, Producto, ProductoHistorialEntry } from '../../core/models/producto';
import { ProductosService } from '../../core/services/productos.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [CommonModule, ModalComponent, BadgeComponent, EmptyStateComponent, SpinnerComponent],
  selector: 'app-historial-modal',
  standalone: true,
  styleUrl: './historial-modal.scss',
  templateUrl: './historial-modal.html',
})
export class HistorialModalComponent implements OnInit {
  readonly producto = input.required<Producto>();

  readonly cerrado = output<void>();

  private readonly productosService = inject(ProductosService);

  readonly registros = signal<ProductoHistorialEntry[]>([]);
  readonly cargando = signal(true);
  error: string | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.error = null;

    this.productosService.historial(this.producto().id).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.registros.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.error = err.message ?? 'No se pudo cargar el historial.';
      },
    });
  }

  etiquetaEstado(estado: string | null): string {
    if (!estado) {
      return '—';
    }
    return ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO] ?? estado;
  }

  tonoEvento(evento: string): string {
    switch (evento) {
      case 'vendida':
        return 'danger';
      case 'reservada':
        return 'warning';
      case 'publicado':
        return 'success';
      default:
        return 'neutral';
    }
  }
}