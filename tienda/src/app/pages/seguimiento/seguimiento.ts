import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CatalogoService } from '../../core/services/catalogo.service';
import { PedidoPublico, EstadoPedido } from '../../core/models/pedido';
import { formatearPrecio } from '../../core/utils/precio';

const ESTADO_ETIQUETA: Record<EstadoPedido, string> = {
  pendiente: 'Pendiente',
  confirmado: 'Confirmado',
  cancelado: 'Cancelado',
  completado: 'Completado',
};

const ESTADO_CLASE: Record<EstadoPedido, string> = {
  pendiente: 'badge-warning',
  confirmado: 'badge-info',
  cancelado: 'badge-neutral',
  completado: 'badge-success',
};

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-seguimiento',
  styleUrl: './seguimiento.scss',
  templateUrl: './seguimiento.html',
})
export class SeguimientoComponent {
  private readonly catalogo = inject(CatalogoService);

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

    this.catalogo.seguirPedido(numero).subscribe({
      next: (res) => {
        this.buscando = false;
        if (res.success && res.data) {
          this.pedido = res.data;
        } else {
          this.error = res.message ?? 'No encontramos un pedido con ese código. Revisalo e intentá de nuevo.';
        }
      },
      error: (err) => {
        this.buscando = false;
        this.error = err.message ?? 'No encontramos un pedido con ese código. Revisalo e intentá de nuevo.';
      },
    });
  }

  estadoEtiqueta(estado: EstadoPedido): string {
    return ESTADO_ETIQUETA[estado] ?? estado;
  }

  estadoClase(estado: EstadoPedido): string {
    return ESTADO_CLASE[estado] ?? 'badge-neutral';
  }

  formatearPrecio(valor: number | string): string {
    return formatearPrecio(valor);
  }
}