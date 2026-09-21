import { Component, OnDestroy, OnInit, Signal, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { CatalogoService } from '../../core/services/catalogo.service';
import { CartService, CartItem } from '../../core/services/cart.service';
import { MetodoEntrega, MetodoPago, CheckoutPayload } from '../../core/models/pedido';
import { ApiError } from '../../core/models/api-response';
import { formatearPrecio } from '../../core/utils/precio';

const TELEFONO_RE = /^[0-9+\-\s()]{7,20}$/;
const QR_IMAGEN = 'assets/images/qr.jpeg';

@Component({
  imports: [CommonModule, ReactiveFormsModule, FormsModule, RouterLink],
  selector: 'app-checkout',
  styleUrl: './checkout.scss',
  templateUrl: './checkout.html',
})
export class CheckoutComponent implements OnInit, OnDestroy {
  private readonly cartService: CartService;
  readonly items: Signal<CartItem[]>;
  readonly subtotal: Signal<number>;

  readonly metodosEntrega = signal<MetodoEntrega[]>([]);
  readonly metodosPago = signal<MetodoPago[]>([]);

  readonly enviando = signal(false);
  readonly errorGeneral = signal<string | null>(null);
  readonly errores = signal<Record<string, string>>({});

  readonly qrImagen = QR_IMAGEN;
  readonly comprobante = signal<File | null>(null);
  readonly comprobantePreview = signal<string | null>(null);
  readonly comprobanteError = signal<string | null>(null);

  readonly cuponCodigo = signal('');
  readonly cuponAplicado = signal(false);
  readonly validandoCupon = signal(false);
  readonly cuponMensaje = signal<string | null>(null);
  readonly cuponError = signal(false);
  readonly cuponDescuento = signal<number>(0);

  readonly form = new FormGroup({
    nombre: new FormControl('', [Validators.required, Validators.minLength(3)]),
    telefono: new FormControl('', [Validators.required, Validators.pattern(TELEFONO_RE)]),
    email: new FormControl('', [Validators.email]),
    ciudad: new FormControl('', [Validators.required, Validators.minLength(2)]),
    direccion: new FormControl('', [Validators.required, Validators.minLength(5)]),
    notas: new FormControl(''),
    metodoPagoId: new FormControl<number | null>(null, [Validators.required]),
    metodoEntregaId: new FormControl<number | null>(null, [Validators.required]),
  });

  constructor(
    private readonly catalogo: CatalogoService,
    cartService: CartService,
    private readonly router: Router,
  ) {
    this.cartService = cartService;
    this.items = cartService.items;
    this.subtotal = cartService.subtotal;
  }

  ngOnInit(): void {
    if (this.items().length === 0) {
      this.router.navigate(['/']);
      return;
    }
    this.catalogo.metodosEntrega().subscribe({
      next: (res) => this.metodosEntrega.set(res.data ?? []),
    });
    this.catalogo.metodosPago().subscribe({
      next: (res) => this.metodosPago.set(res.data ?? []),
    });
  }

  formatearPrecio(valor: number | string): string {
    return formatearPrecio(valor);
  }

  costoEnvio(): number {
    const entrega = this.metodosEntrega().find(
      (metodo) => metodo.id === this.form.value.metodoEntregaId,
    );
    return entrega ? Number(entrega.costo) : 0;
  }

  tieneCosto(costo: string): boolean {
    return Number(costo) > 0;
  }

  total(): number {
    return Math.max(
      0,
      this.subtotal() + this.costoEnvio() - (this.cuponAplicado() ? this.cuponDescuento() : 0),
    );
  }

  pagoEsQR(): boolean {
    const seleccionado = this.metodosPago().find(
      (metodo) => metodo.id === this.form.value.metodoPagoId,
    );
    return seleccionado !== undefined && seleccionado.nombre.toLowerCase() === 'qr';
  }

  onComprobante(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    this.comprobante.set(archivo);

    const previaPrevia = this.comprobantePreview();
    if (previaPrevia) {
      URL.revokeObjectURL(previaPrevia);
    }
    this.comprobantePreview.set(archivo ? URL.createObjectURL(archivo) : null);

    if (archivo) {
      this.comprobanteError.set(null);
    }
  }

  ngOnDestroy(): void {
    const previa = this.comprobantePreview();
    if (previa) {
      URL.revokeObjectURL(previa);
    }
  }

  descargarQR(): void {
    const enlace = document.createElement('a');
    enlace.href = this.qrImagen;
    enlace.download = 'qr-everly-pago.jpeg';
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
  }

  erroresDe(campo: string): string | null {
    return this.errores()[campo] ?? null;
  }

  limpiarCupon(event: Event): string {
    return (event.target as HTMLInputElement).value.trim().toUpperCase();
  }

  aplicarCupon(): void {
    const codigo = this.cuponCodigo().trim().toUpperCase();
    if (!codigo || this.cuponAplicado()) {
      return;
    }

    this.validandoCupon.set(true);
    this.cuponMensaje.set(null);
    this.cuponError.set(false);

    this.catalogo.validarCupon(codigo, this.subtotal()).subscribe({
      next: (res) => {
        this.validandoCupon.set(false);
        const datos = res.data;
        if (datos?.valido) {
          this.cuponAplicado.set(true);
          this.cuponDescuento.set(Number(datos.descuento) || 0);
          this.cuponMensaje.set(datos.mensaje ?? 'Cupón aplicado correctamente.');
        } else {
          this.cuponAplicado.set(false);
          this.cuponDescuento.set(0);
          this.cuponError.set(true);
          this.cuponMensaje.set(datos?.mensaje ?? 'El cupón no es válido para este pedido.');
        }
      },
      error: () => {
        this.validandoCupon.set(false);
        this.cuponAplicado.set(false);
        this.cuponDescuento.set(0);
        this.cuponError.set(true);
        this.cuponMensaje.set('No pudimos validar el cupón. Intentá de nuevo.');
      },
    });
  }

  enviar(): void {
    this.errorGeneral.set(null);
    this.errores.set({});
    this.comprobanteError.set(null);
    this.form.markAllAsTouched();

    const valores = this.form.value;
    const faltaComprobante = this.pagoEsQR() && !this.comprobante();
    if (
      !this.form.valid ||
      this.items().length === 0 ||
      valores.metodoPagoId === null ||
      valores.metodoEntregaId === null ||
      !valores.nombre ||
      !valores.telefono ||
      !valores.ciudad ||
      !valores.direccion ||
      faltaComprobante ||
      this.enviando()
    ) {
      if (faltaComprobante) {
        this.comprobanteError.set('Debes adjuntar el comprobante de pago para confirmar tu pedido.');
      }
      return;
    }

    const payload: CheckoutPayload = {
      productos: this.items().map((item) => item.producto_id),
      nombre: valores.nombre.trim(),
      telefono: valores.telefono.trim(),
      email: valores.email?.trim() || null,
      ciudad: valores.ciudad.trim(),
      direccion: valores.direccion.trim(),
      notas: valores.notas?.trim() || null,
      metodo_pago_id: valores.metodoPagoId ?? null,
      metodo_entrega_id: valores.metodoEntregaId ?? null,
      codigo_cupon: this.cuponAplicado()
        ? (this.cuponCodigo().trim().toUpperCase() || null)
        : null,
    };

    this.enviando.set(true);
    this.catalogo.crearPedido(payload, this.comprobante()).subscribe({
      next: (res) => {
        this.enviando.set(false);
        if (res.data) {
          this.cartService.vaciar();
          try {
            sessionStorage.setItem('everly_ultimo_pedido', JSON.stringify(res.data));
          } catch {
            // Sin persistencia local no bloqueamos la navegación.
          }
          this.router.navigate(['/confirmacion'], { state: { order: res.data } });
        }
      },
      error: (error: ApiError) => {
        this.enviando.set(false);
        if (error.errors) {
          const mapeados: Record<string, string> = {};
          for (const [clave, mensajes] of Object.entries(error.errors)) {
            if (mensajes.length > 0) {
              if (clave === 'metodo_pago_id') {
                mapeados['metodoPagoId'] = mensajes[0];
              } else if (clave === 'metodo_entrega_id') {
                mapeados['metodoEntregaId'] = mensajes[0];
              } else if (clave === 'comprobante') {
                this.comprobanteError.set(mensajes[0]);
              } else {
                mapeados[clave] = mensajes[0];
              }
            }
          }
          this.errores.set(mapeados);
        }
        this.errorGeneral.set(
          error.message || 'No pudimos procesar el pedido. Verificá tus datos e intentá de nuevo.',
        );
      },
    });
  }
}