import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { PedidoPublico, EstadoPedido } from '../../core/models/pedido';
import { formatearPrecio } from '../../core/utils/precio';

const STORAGE_KEY = 'everly_ultimo_pedido';
const WHATSAPP_TIENDA = '59162640247';

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

  enviarWhatsApp(): void {
    const pedido = this.order;
    if (!pedido) {
      return;
    }

    const detalle = (pedido.items ?? [])
      .map((item) => {
        const talle = item.producto_talla ? ` · Talle ${item.producto_talla}` : '';
        return `  \u{1F6CD}\u{FE0F} ${item.producto_nombre}${talle} — ${formatearPrecio(item.precio_unitario)}`;
      })
      .join('\n');

    const cliente = pedido.cliente;
    const direccion = [cliente?.ciudad, cliente?.direccion].filter(Boolean).join(', ');

    const mensaje = [
      '\u{2728} ¡Hola! \u{2728}',
      'Quiero confirmar mi compra \u{1F6CD}\u{FE0F}\u{1F49D}',
      '',
      '\u{1F4E6} *Número de pedido:*',
      `   ${pedido.numero_pedido}`,
      '',
      '\u{1F6D2} *Datos del cliente:*',
      `   \u{1F464} *Nombre:* ${cliente?.nombre ?? ''}`,
      `   \u{1F4DE} *Teléfono:* ${cliente?.telefono ?? ''}`,
      `   \u{1F4CD} *Dirección:* ${direccion || '—'}`,
      '',
      '\u{1F9FE} *Detalle de mi pedido:*',
      detalle,
      '',
      '\u{1F4B0} *Resumen:*',
      `   \u{1F4B5} Subtotal: ${formatearPrecio(pedido.subtotal)}`,
      `   \u{1F69A} Envío (${pedido.metodo_entrega ?? '—'}): ${formatearPrecio(pedido.costo_envio)}`,
      `   \u{1F389} *Total: ${formatearPrecio(pedido.total)}*`,
      '',
      `\u{1F4B3} *Pago:* ${pedido.metodo_pago ?? '—'} (comprobante adjunto \u{1F4CE})`,
      `\u{1F69B} *Entrega:* ${pedido.metodo_entrega ?? '—'}`,
      '',
      pedido.comprobante_url
        ? `Adjunto la foto del comprobante \u{1F4CE}: ${pedido.comprobante_url}`
        : 'Adjunto la foto del comprobante en este chat \u{1F64F}',
      '',
      '¡Muchas gracias! \u{1F496}',
      'Espero que disfrutes tus prendas \u{2728}\u{1F970}',
    ].join('\n');

    window.open(`https://api.whatsapp.com/send?phone=${WHATSAPP_TIENDA}&text=${encodeURIComponent(mensaje)}`, '_blank', 'noopener');
  }
}