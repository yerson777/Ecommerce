import { Component, OnInit, OnDestroy, signal, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { interval, Subscription } from 'rxjs';
import { PedidoPublico, EstadoPedido } from '../../core/models/pedido';
import { ConfiguracionService } from '../../core/services/configuracion.service';
import { CatalogoService } from '../../core/services/catalogo.service';
import { etiquetaEstadoPedido } from '../../core/utils/pedido-formato';
import { formatearPrecio } from '../../core/utils/precio';

const STORAGE_KEY = 'everly_ultimo_pedido';
const STORAGE_AVISOS_KEY = 'everly_ultimo_pedido_avisos';

@Component({
  imports: [CommonModule, RouterLink],
  selector: 'app-confirmacion',
  styleUrl: './confirmacion.scss',
  templateUrl: './confirmacion.html',
})
export class ConfirmacionComponent implements OnInit, OnDestroy {
  order: PedidoPublico | null = null;
  avisos: string[] = [];

  private readonly catalogo = inject(CatalogoService);
  readonly pagoConfirmado = signal(false);
  private refrescoSuscripcion = new Subscription();

  readonly claseBadge = computed(() => {
    if (this.pagoConfirmado()) {
      return 'badge badge-pago-confirmado';
    }
    return 'badge badge-pedido-recibido';
  });

  constructor(
    private readonly router: Router,
    private readonly config: ConfiguracionService,
  ) {}

  ngOnInit(): void {
    const navegacion = this.router.getCurrentNavigation();
    const delEstado = navegacion?.extras.state?.['order'] as PedidoPublico | undefined;
    const avisosEstado = navegacion?.extras.state?.['avisos'] as string[] | undefined;

    this.order = delEstado ?? this.leerAlmacenado();
    this.avisos = avisosEstado ?? this.leerAvisos();

    if (!this.order) {
      this.router.navigate(['/']);
      return;
    }

    this.consultarEstado();
    this.iniciarRefresco();
    this.iniciarListenerVisibilidad();
  }

  ngOnDestroy(): void {
    this.refrescoSuscripcion.unsubscribe();
    window.removeEventListener('visibilitychange', this.onVisibilityChange);
  }

  private onVisibilityChange: () => void = () => {};

  private leerAvisos(): string[] {
    try {
      const crudo = sessionStorage.getItem(STORAGE_AVISOS_KEY);
      if (!crudo) {
        return [];
      }
      const datos: unknown = JSON.parse(crudo);
      return Array.isArray(datos) ? (datos as string[]) : [];
    } catch {
      return [];
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

  private consultarEstado(): void {
    if (!this.order) {
      return;
    }
    this.catalogo.seguirPedido(this.order.numero_pedido).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.order = res.data;
          if (res.data.estado === 'confirmado' || res.data.estado === 'completado') {
            this.pagoConfirmado.set(true);
          }
        }
      },
      error: () => undefined,
    });
  }

  private iniciarRefresco(): void {
    if (this.pagoConfirmado() || this.order?.estado !== 'pendiente') {
      return;
    }
    this.refrescoSuscripcion = interval(30000).subscribe(() => {
      this.consultarEstado();
    });
  }

  private iniciarListenerVisibilidad(): void {
    this.onVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        this.consultarEstado();
      }
    };
    window.addEventListener('visibilitychange', this.onVisibilityChange);
  }

  readonly formatearPrecio = formatearPrecio;

  estadoEtiqueta(estado: EstadoPedido): string {
    return etiquetaEstadoPedido(estado);
  }

  imprimir(): void {
    const codigo = this.order?.numero_pedido ?? '';
    const titleAnterior = document.title;
    document.title = `Pedido ${codigo}`;
    document.body.classList.add('imprimiendo-pedido');

    const afterPrint = () => {
      document.body.classList.remove('imprimiendo-pedido');
      document.title = titleAnterior;
      window.removeEventListener('afterprint', afterPrint);
    };
    window.addEventListener('afterprint', afterPrint);
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

    window.open(
      `https://api.whatsapp.com/send?phone=${this.config.whatsapp()}&text=${encodeURIComponent(mensaje)}`,
      '_blank',
      'noopener',
    );
  }
}
