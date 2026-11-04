import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { MiPedidoDetalle } from '../../core/models/mi-pedido';
import { MiPedidoService } from '../../core/services/mi-pedido.service';
import {
  etiquetaEstadoPago,
  etiquetaEstadoPedido,
  formatearFecha,
} from '../../core/utils/pedido-formato';
import { formatearPrecio } from '../../core/utils/precio';

@Component({
  imports: [RouterLink],
  selector: 'app-mi-pedido-detalle',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './mis-pedido-detalle.html',
})
export class MiPedidoDetalleComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly pedidosService = inject(MiPedidoService);

  readonly pedido = signal<MiPedidoDetalle | null>(null);
  readonly cargando = signal(true);
  readonly error = signal<string | null>(null);

  readonly formatearPrecio = formatearPrecio;
  readonly formatearFecha = formatearFecha;
  readonly etiquetaEstado = etiquetaEstadoPedido;
  readonly etiquetaPago = etiquetaEstadoPago;

  ngOnInit(): void {
    const numero = this.route.snapshot.paramMap.get('numero') ?? '';

    if (!numero) {
      this.error.set('No encontramos ese pedido.');
      this.cargando.set(false);
      return;
    }

    this.pedidosService.detalle(numero).subscribe({
      next: (pedido) => {
        this.pedido.set(pedido);
        this.cargando.set(false);
      },
      error: (err: ApiError) => {
        this.error.set(err.message ?? 'No pudimos cargar el pedido.');
        this.cargando.set(false);
      },
    });
  }

  tieneDescuento(valor: string | number | null | undefined): boolean {
    return Number(valor ?? 0) > 0;
  }
}
