import {
  Component,
  computed,
  ElementRef,
  OnDestroy,
  OnInit,
  Signal,
  effect,
  inject,
  signal,
  WritableSignal,
  viewChild,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormControl,
  FormGroup,
  FormsModule,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { CatalogoService } from '../../core/services/catalogo.service';
import { CartService, CartItem } from '../../core/services/cart.service';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';
import { ConfiguracionService } from '../../core/services/configuracion.service';
import { CheckoutPayload, MetodoEntrega, MetodoPago } from '../../core/models/pedido';
import { ApiError } from '../../core/models/api-response';
import { formatearPrecio } from '../../core/utils/precio';

const TELEFONO_RE = /^[0-9+\-\s()]{7,20}$/;
const QR_IMAGEN = 'assets/images/qr.jpeg';
const MENSAJE_FALLO_PROCESO =
  'No pudimos procesar tu pedido. Intenta de nuevo o escríbenos por WhatsApp.';

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

  readonly auth = inject(ClienteAuthService);

  readonly metodosEntrega = signal<MetodoEntrega[]>([]);
  readonly metodosPago = signal<MetodoPago[]>([]);
  readonly duracionReservaHoras = computed(() => this.config.datos().duracion_reserva_horas ?? 24);

  readonly enviando = signal(false);
  readonly errorGeneral = signal<string | null>(null);
  readonly errores = signal<Record<string, string>>({});

  readonly qrImagen = QR_IMAGEN;
  readonly comprobante = signal<File | null>(null);
  readonly comprobantePreview = signal<string | null>(null);
  readonly comprobanteError = signal<string | null>(null);
  readonly cuentaCopiada = signal(false);
  readonly clienteExistente = signal<boolean | null>(null);

  private readonly videoCamara = viewChild<ElementRef<HTMLVideoElement>>('videoCamara');
  private readonly inputCamara = viewChild<ElementRef<HTMLInputElement>>('comprobanteCamara');
  private readonly streamCamara = signal<MediaStream | null>(null);
  readonly camaraAbierta = signal(false);
  readonly camaraError = signal<string | null>(null);

  readonly validandoCupon = signal(false);
  readonly cuponMensaje = signal<string | null>(null);
  readonly cuponError = signal(false);

  readonly cuponAplicado = computed(() => this.cartService.cuponAplicado());
  readonly cuponDescuento = computed(() => this.cartService.cuponDescuento());
  readonly cuponCodigo: WritableSignal<string>;

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
    private readonly config: ConfiguracionService,
  ) {
    this.cartService = cartService;
    this.items = cartService.items;
    this.subtotal = cartService.subtotal;
    this.cuponCodigo = this.cartService.cuponBorrador;

    // Conecta el stream de la cámara al <video> en cuanto el modal se renderiza.
    effect(() => {
      const video = this.videoCamara()?.nativeElement;
      const stream = this.streamCamara();
      if (video && stream && video.srcObject !== stream) {
        video.srcObject = stream;
        void video.play().catch(() => undefined);
      }
    });
  }

  ngOnInit(): void {
    if (this.items().length === 0) {
      this.router.navigate(['/']);
      return;
    }

    // Con sesión iniciada, autocompleta los datos de entrega. El backend
    // asocia el pedido a la cuenta a partir del token; como invitado todo
    // sigue funcionando igual.
    const cliente = this.auth.cliente();
    if (cliente) {
      this.form.patchValue({
        nombre: cliente.nombre,
        telefono: cliente.telefono ?? '',
        email: cliente.email,
        ciudad: cliente.ciudad ?? '',
        direccion: cliente.direccion ?? '',
      });
    }

    this.catalogo.metodosEntrega().subscribe({
      next: (res) => this.metodosEntrega.set(res.data ?? []),
    });
    this.cargarMetodosPago();

    // Si hay un cupón aplicado desde el carrito, revalidarlo contra el backend.
    // Si ya no es válido, se quita y se avisa al cliente.
    this.revalidarCuponGuardado();

    // Aviso suave si el teléfono o el correo ya están registrados (sin revelar datos).
    this.form.controls.telefono.valueChanges.subscribe(() => {
      this.clienteExistente.set(null);
    });
    this.form.controls.email.valueChanges.subscribe(() => {
      this.clienteExistente.set(null);
    });
  }

  private cargarMetodosPago(): void {
    this.catalogo.metodosPagoDetalle().subscribe({
      next: (res) => {
        if (res.data && res.data.length > 0) {
          this.metodosPago.set(res.data);
        } else {
          this.cargarMetodosPagoSimple();
        }
      },
      error: () => this.cargarMetodosPagoSimple(),
    });
  }

  private cargarMetodosPagoSimple(): void {
    this.catalogo.metodosPago().subscribe({
      next: (res) => this.metodosPago.set(res.data ?? []),
      error: () => this.metodosPago.set([]),
    });
  }

  consultarClienteExistente(): void {
    const telefono = this.form.controls.telefono.value?.trim() || '';
    const email = this.form.controls.email.value?.trim() || null;
    if (!telefono && !email) {
      this.clienteExistente.set(null);
      return;
    }
    this.catalogo.clienteExiste(telefono, email).subscribe({
      next: (existe) => this.clienteExistente.set(existe),
      error: () => this.clienteExistente.set(null),
    });
  }

  readonly formatearPrecio = formatearPrecio;

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

  metodoPagoSeleccionado(): MetodoPago | undefined {
    return this.metodosPago().find(
      (metodo) => metodo.id === this.form.value.metodoPagoId,
    );
  }

  pagoEsQR(): boolean {
    const seleccionado = this.metodoPagoSeleccionado();
    if (!seleccionado) {
      return false;
    }

    return (
      (seleccionado.codigo ?? '').toLowerCase() === 'qr' ||
      seleccionado.nombre.toLowerCase() === 'qr'
    );
  }

  /** El método de pago seleccionado es "Transferencia". */
  pagoEsTransferencia(): boolean {
    const seleccionado = this.metodoPagoSeleccionado();
    if (!seleccionado) {
      return false;
    }

    return (
      (seleccionado.codigo ?? '').toLowerCase() === 'transferencia' ||
      seleccionado.nombre.toLowerCase() === 'transferencia'
    );
  }

  /**
   * Métodos de pago que exigen comprobante de la operación (QR y
   * Transferencia). Ambos usan el mismo bloque de comprobante.
   */
  pagoRequiereComprobante(): boolean {
    return this.pagoEsQR() || this.pagoEsTransferencia();
  }

  qrImagenActual(): string {
    return this.metodoPagoSeleccionado()?.qr_url ?? QR_IMAGEN;
  }

  titularPago(): string {
    return this.metodoPagoSeleccionado()?.titular ?? 'Gutierrez Choque Yerson';
  }

  instruccionesPago(): string {
    return (
      this.metodoPagoSeleccionado()?.instrucciones ??
      'Descarga el QR, págalo desde tu banco y adjunta la foto del comprobante.'
    );
  }

  datosPago(): Array<{ etiqueta: string; valor: string }> {
    const metodo = this.metodoPagoSeleccionado();
    if (!metodo) {
      return [];
    }

    const datos = [
      { etiqueta: 'Titular', valor: metodo.titular },
      { etiqueta: 'Banco', valor: metodo.banco },
      { etiqueta: 'Número de cuenta', valor: metodo.numero_cuenta },
    ];

    return datos
      .filter((dato): dato is { etiqueta: string; valor: string } => !!dato.valor)
      .map((dato) => ({ etiqueta: dato.etiqueta, valor: String(dato.valor) }));
  }

  /** Datos de la cuenta bancaria para el método "Transferencia". */
  datosTransferencia(): Array<{ etiqueta: string; valor: string }> {
    const metodo = this.metodoPagoSeleccionado();
    if (!metodo) {
      return [];
    }

    const datos = [
      { etiqueta: 'Cuenta', valor: metodo.numero_cuenta },
      { etiqueta: 'A nombre de', valor: metodo.titular },
      { etiqueta: 'Banco', valor: metodo.banco },
    ];

    return datos
      .filter((dato): dato is { etiqueta: string; valor: string } => !!dato.valor)
      .map((dato) => ({ etiqueta: dato.etiqueta, valor: String(dato.valor) }));
  }

  /** Número de cuenta a copiar (método "Transferencia"). */
  numeroCuenta(): string {
    return this.metodoPagoSeleccionado()?.numero_cuenta ?? '';
  }

  /** Copia el número de cuenta al portapapeles. */
  async copiarCuenta(): Promise<void> {
    const cuenta = this.numeroCuenta().trim();
    if (!cuenta) {
      return;
    }

    try {
      await navigator.clipboard.writeText(cuenta);
    } catch {
      // Sin soporte de portapapeles (contexto no seguro): copia manual.
      const area = document.createElement('textarea');
      area.value = cuenta;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      try {
        document.execCommand('copy');
      } catch {
        // Si tampoco funciona, el usuario puede copiar el número a mano.
      }
      area.remove();
    }

    this.cuentaCopiada.set(true);
    setTimeout(() => this.cuentaCopiada.set(false), 2000);
  }

  onComprobante(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    this.aplicarComprobante(archivo);
    // Permite volver a elegir/tomar el mismo archivo más adelante.
    input.value = '';
  }

  eliminarComprobante(): void {
    this.comprobanteError.set(null);
    this.aplicarComprobante(null);
  }

  private aplicarComprobante(archivo: File | null): void {
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

  /**
   * Abre la cámara con getUserMedia (escritorio y móvil). Si el navegador no
   * la soporta o la conexión no es segura, cae al input de archivo, que en
   * móvil abre la cámara nativa gracias a `capture`.
   */
  async tomarFoto(): Promise<void> {
    this.comprobanteError.set(null);
    const soportada =
      typeof navigator !== 'undefined' &&
      !!navigator.mediaDevices?.getUserMedia &&
      window.isSecureContext;

    if (!soportada) {
      this.inputCamara()?.nativeElement.click();
      return;
    }

    this.camaraError.set(null);
    this.camaraAbierta.set(true);

    try {
      this.streamCamara.set(await this.obtenerStream());
    } catch {
      this.streamCamara.set(null);
      this.camaraError.set(
        'No pudimos acceder a la cámara. Revisá los permisos del navegador o elegí un archivo.',
      );
    }
  }

  private async obtenerStream(): Promise<MediaStream> {
    const media = navigator.mediaDevices;
    try {
      return await media.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false,
      });
    } catch {
      return await media.getUserMedia({ video: true, audio: false });
    }
  }

  capturarFoto(): void {
    const video = this.videoCamara()?.nativeElement;
    if (!video || !video.videoWidth || !video.videoHeight) {
      return;
    }

    const lienzo = document.createElement('canvas');
    lienzo.width = video.videoWidth;
    lienzo.height = video.videoHeight;

    const contexto = lienzo.getContext('2d');
    if (!contexto) {
      return;
    }
    contexto.drawImage(video, 0, 0, lienzo.width, lienzo.height);

    lienzo.toBlob(
      (blob) => {
        if (!blob) {
          return;
        }
        const archivo = new File([blob], `comprobante-${Date.now()}.jpg`, {
          type: 'image/jpeg',
        });
        this.aplicarComprobante(archivo);
        this.cerrarCamara();
      },
      'image/jpeg',
      0.9,
    );
  }

  cerrarCamara(): void {
    this.streamCamara()?.getTracks().forEach((pista) => pista.stop());
    this.streamCamara.set(null);
    this.camaraAbierta.set(false);
    this.camaraError.set(null);
  }

  ngOnDestroy(): void {
    this.streamCamara()?.getTracks().forEach((pista) => pista.stop());
    const previa = this.comprobantePreview();
    if (previa) {
      URL.revokeObjectURL(previa);
    }
  }

  descargarQR(): void {
    const imagen = this.qrImagenActual();
    const enlace = document.createElement('a');
    enlace.href = imagen;
    enlace.download = imagen === QR_IMAGEN ? 'qr-everly-pago.jpeg' : 'qr-pago';
    enlace.target = '_blank';
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

  private revalidarCuponGuardado(): void {
    if (!this.cartService.cuponAplicado()) {
      return;
    }

    const codigo = this.cartService.cuponCodigo();
    this.validandoCupon.set(true);

    this.catalogo.validarCupon(codigo, this.subtotal(), {
      productos: this.items().map((item) => item.producto_id),
    }).subscribe({
      next: (res) => {
        this.validandoCupon.set(false);
        if (!res.data?.valido) {
          this.cartService.limpiarCupon();
          this.cuponMensaje.set(res.data?.mensaje ?? 'El cupón ya no es válido y se eliminó.');
          this.cuponError.set(true);
        }
      },
      error: () => {
        this.validandoCupon.set(false);
        this.cartService.limpiarCupon();
        this.cuponMensaje.set('No pudimos revalidar el cupón. Se eliminó del pedido.');
        this.cuponError.set(true);
      },
    });
  }

  aplicarCupon(): void {
    const codigo = this.cuponCodigo().trim().toUpperCase();
    if (!codigo || this.cuponAplicado() || this.validandoCupon()) {
      return;
    }

    this.validandoCupon.set(true);
    this.cuponMensaje.set(null);
    this.cuponError.set(false);

    const contacto = this.form.value;
    this.catalogo.validarCupon(codigo, this.subtotal(), {
      productos: this.items().map((item) => item.producto_id),
      telefono: contacto.telefono ?? null,
      email: contacto.email ?? null,
    }).subscribe({
      next: (res) => {
        this.validandoCupon.set(false);
        const datos = res.data;
        if (datos?.valido) {
          this.cartService.aplicarCupon(codigo, Number(datos.descuento) || 0);
          this.cuponMensaje.set(datos.mensaje ?? 'Cupón aplicado correctamente.');
        } else {
          this.cartService.limpiarCupon();
          this.cuponError.set(true);
          this.cuponMensaje.set(datos?.mensaje ?? 'El cupón no es válido para este pedido.');
        }
      },
      error: () => {
        this.validandoCupon.set(false);
        this.cartService.limpiarCupon();
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
    const faltaComprobante = this.pagoRequiereComprobante() && !this.comprobante();
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
        this.comprobanteError.set(
          'Debes adjuntar el comprobante de pago para confirmar tu pedido.',
        );
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
      cupon_codigo: this.cuponAplicado() ? this.cartService.cuponCodigo() : null,
      precios_esperados: this.items().map((item) => ({
        producto_id: item.producto_id,
        precio: Number(item.precio),
      })),
    };

    this.enviando.set(true);
    this.catalogo.crearPedido(payload, this.comprobante()).subscribe({
      next: (res) => {
        this.enviando.set(false);
        if (res.data) {
          this.cartService.vaciar();
          try {
            sessionStorage.setItem('everly_ultimo_pedido', JSON.stringify(res.data));
            sessionStorage.setItem(
              'everly_ultimo_pedido_avisos',
              JSON.stringify(res.avisos ?? []),
            );
          } catch {
            // Sin persistencia local no bloqueamos la navegación.
          }
          this.router.navigate(['/confirmacion'], {
            state: { order: res.data, avisos: res.avisos ?? [] },
          });
        }
      },
      error: (error: ApiError) => {
        this.enviando.set(false);
        if (error.status === 422) {
          // Errores de validación: mensaje específico junto a cada campo.
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
          this.errorGeneral.set('Revisá los datos marcados e intentá de nuevo.');
          return;
        }
        if (error.status && error.status >= 400 && error.status < 500) {
          // Rechazos con sentido (ej. 409 por stock ya reservado).
          this.errorGeneral.set(error.message || MENSAJE_FALLO_PROCESO);
          return;
        }
        // Error de servidor o de conexión.
        this.errorGeneral.set(MENSAJE_FALLO_PROCESO);
      },
    });
  }
}
