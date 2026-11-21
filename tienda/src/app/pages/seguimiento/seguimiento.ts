import { Component, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CatalogoService } from '../../core/services/catalogo.service';
import { PedidoPublico } from '../../core/models/pedido';
import { CartelEstadoPedido, cartelEstadoPedido, pedidoPagado } from '../../core/utils/estado-pedido';
import { formatearPrecio } from '../../core/utils/precio';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-seguimiento',
  styleUrl: './seguimiento.scss',
  templateUrl: './seguimiento.html',
})
export class SeguimientoComponent {
  private readonly catalogo = inject(CatalogoService);
  private readonly cdr = inject(ChangeDetectorRef);

  numero = '';
  pedido: PedidoPublico | null = null;
  buscando = false;
  error: string | null = null;

  buscar(): void {
    const numero = this.numero.trim();

    if (!numero || this.buscando) {
      return;
    }

    this.buscando = true;
    this.error = null;
    this.pedido = null;
    this.cdr.markForCheck();

    this.catalogo.seguirPedido(numero).subscribe({
      next: (res) => {
        this.buscando = false;
        if (res.success && res.data) {
          this.pedido = res.data;
        } else {
          this.error =
            res.message ?? 'No encontramos un pedido con ese código. Revisalo e intentá de nuevo.';
        }
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.buscando = false;
        this.error =
          err.message ?? 'No encontramos un pedido con ese código. Revisalo e intentá de nuevo.';
        this.cdr.markForCheck();
      },
    });
  }

  cartel(): CartelEstadoPedido {
    return cartelEstadoPedido(
      this.pedido?.estado ?? '',
      pedidoPagado(this.pedido),
      'seguimiento',
    );
  }

  readonly formatearPrecio = formatearPrecio;
}
