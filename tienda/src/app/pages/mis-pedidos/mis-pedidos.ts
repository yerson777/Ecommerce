import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { MiPedido } from '../../core/models/mi-pedido';
import { MiPedidoService } from '../../core/services/mi-pedido.service';
import {
  etiquetaEstadoPago,
  etiquetaEstadoPedido,
  formatearFecha,
} from '../../core/utils/pedido-formato';
import { formatearPrecio } from '../../core/utils/precio';

@Component({
  imports: [RouterLink],
  selector: 'app-mis-pedidos',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './mis-pedidos.html',
})
export class MisPedidosComponent implements OnInit {
  private readonly pedidosService = inject(MiPedidoService);

  readonly pedidos = signal<MiPedido[]>([]);
  readonly cargando = signal(true);
  readonly error = signal<string | null>(null);
  readonly pagina = signal(1);
  readonly ultimaPagina = signal(1);
  readonly total = signal(0);

  readonly formatearPrecio = formatearPrecio;
  readonly formatearFecha = formatearFecha;
  readonly etiquetaEstado = etiquetaEstadoPedido;
  readonly etiquetaPago = etiquetaEstadoPago;

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.error.set(null);

    this.pedidosService.listar(this.pagina()).subscribe({
      next: (res) => {
        this.pedidos.set(res.items);
        this.ultimaPagina.set(res.last_page);
        this.total.set(res.total);
        this.cargando.set(false);
      },
      error: (err: ApiError) => {
        this.cargando.set(false);
        this.error.set(err.message ?? 'No pudimos cargar tus pedidos.');
      },
    });
  }

  irA(pagina: number): void {
    if (pagina < 1 || pagina > this.ultimaPagina() || pagina === this.pagina()) {
      return;
    }
    this.pagina.set(pagina);
    this.cargar();
  }
}
