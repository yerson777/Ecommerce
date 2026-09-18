import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { PedidoPublico, EstadoPedido } from '../../core/models/pedido';
import { formatearPrecio } from '../../core/utils/precio';

const STORAGE_KEY = 'everly_ultimo_pedido';

const ESTADO_ETIQUETA: Record<EstadoPedido, string> = {
  pendiente: 'Pendiente de pago',
  confirmado: 'Confirmado',
  cancelado: 'Cancelado',
  completado: 'Completado',
};

@Component({
  imports: [CommonModule, RouterLink],
  selector: 'app-confirmacion',
  styleUrl: './confirmacion.scss',
  templateUrl: './confirmacion.html',
})
export class ConfirmacionComponent implements OnInit {
  order: PedidoPublico | null = null;

  constructor(private readonly router: Router) {}

  ngOnInit(): void {
    const delEstado = this.router.getCurrentNavigation()?.extras.state?.['order'] as
      | PedidoPublico
      | undefined;

    this.order = delEstado ?? this.leerAlmacenado();

    if (!this.order) {
      this.router.navigate(['/']);
    }
  }

  private leerAlmacenado(): PedidoPublico | null {
    try {
      const crudo = sessionStorage.getItem(STORAGE_KEY);

      if (!crudo) {
        return null;
      }

      const datos: unknown = JSON.parse(crudo);

      if (typeof datos === 'object' && datos !== null && 'numero_pedido' in datos) {
        return datos as PedidoPublico;
      }
    } catch {
      // Sin datos almacenados o JSON corrupto.
    }

    return null;
  }

  formatearPrecio(valor: number | string): string {
    return formatearPrecio(valor);
  }

  estadoEtiqueta(estado: EstadoPedido): string {
    return ESTADO_ETIQUETA[estado] ?? estado;
  }

  imprimir(): void {
    window.print();
  }
}