import { Component, ElementRef, OnDestroy, ViewChild, inject, signal } from '@angular/core';
import { formatearMoneda } from '../../core/utils/formato';
import { FormsModule } from '@angular/forms';
import {
  ConfiguracionHistorial,
  ConfiguracionTienda,
  MetodoEntregaConfig,
  MetodoPagoConfig,
} from '../../core/models/configuracion';
import { ConfiguracionService } from '../../core/services/configuracion.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

const TIPOS_LOGO = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_LOGO_BYTES = 2 * 1024 * 1024;

@Component({
  imports: [FormsModule, BadgeComponent, NotificationCenterComponent, SpinnerComponent],
  selector: 'app-configuracion',
  standalone: true,
  styleUrl: './configuracion.scss',
  templateUrl: './configuracion.html',
})
export class ConfiguracionComponent implements OnDestroy {
  @ViewChild('logoInput') private logoInput?: ElementRef<HTMLInputElement>;

  private readonly service = inject(ConfiguracionService);
  private readonly toast = inject(ToastService);

  readonly metodosPago = signal<MetodoPagoConfig[]>([]);
  readonly metodosEntrega = signal<MetodoEntregaConfig[]>([]);
  readonly cargando = signal(true);
  readonly accionandoId = signal<number | null>(null);
  readonly guardandoCosto = signal<number | null>(null);
  readonly guardandoPagoId = signal<number | null>(null);
  readonly qrArchivos = signal<Record<number, File>>({});
  readonly qrPreviews = signal<Record<number, string>>({});
  readonly qrErrores = signal<Record<number, string>>({});
  readonly quitarQr = signal<Record<number, boolean>>({});

  readonly cargandoTienda = signal(true);
  readonly guardandoTienda = signal(false);
  readonly logoPreview = signal<string | null>(null);
  readonly logoError = signal<string | null>(null);
  readonly historial = signal<ConfiguracionHistorial[]>([]);

  readonly logoArchivo = signal<File | null>(null);
  readonly quitarLogo = signal(false);

  datos: ConfiguracionTienda = this.datosVacios();

  private logoBlobUrl: string | null = null;

  constructor() {
    this.cargar();
  }

  ngOnDestroy(): void {
    this.revocarLogoBlob();
    this.revocarQrBlobs();
  }

  cargar(): void {
    this.cargando.set(true);

    this.service.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosPago.set(res.data);
        }
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar los métodos de pago.');
      },
    });

    this.service.metodosEntrega().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosEntrega.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar los métodos de entrega.'),
    });

    this.cargarTienda();
    this.cargarHistorial();
  }

  private cargarTienda(): void {
    this.cargandoTienda.set(true);

    this.service.datosTienda().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.datos = res.data;
          this.revocarLogoBlob();
          this.logoPreview.set(res.data.logo_url);
        }
        this.cargandoTienda.set(false);
      },
      error: (err) => {
        this.cargandoTienda.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar los datos de la tienda.');
      },
    });
  }

  private cargarHistorial(): void {
    this.service.historialTienda().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.historial.set(res.data);
        }
      },
      error: () => undefined,
    });
  }

  alternarPago(metodo: MetodoPagoConfig): void {
    if (this.accionandoId() !== null) {
      return;
    }

    if (metodo.activo && this.metodosPago().filter((item) => item.activo).length <= 1) {
      this.toast.error('Debe quedar al menos un método de pago activo.');
      return;
    }

    const anterior = metodo.activo;
    metodo.activo = !anterior;
    this.accionandoId.set(metodo.id);

    this.service.actualizarMetodoPago(metodo.id, { activo: metodo.activo }).subscribe({
      next: (res) => {
        this.accionandoId.set(null);
        if (res.success && res.data) {
          metodo.activo = res.data.activo;
          this.toast.success(`Método de pago ${metodo.activo ? 'activado' : 'desactivado'}.`);
        }
      },
      error: (err) => {
        this.accionandoId.set(null);
        metodo.activo = anterior;
        this.toast.error(err.message ?? 'No se pudo actualizar el método de pago.');
      },
    });
  }

  onQrSeleccionado(metodo: MetodoPagoConfig, evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0];
    this.qrErrores.update((errores) => ({ ...errores, [metodo.id]: '' }));

    if (!archivo) {
      this.limpiarQrArchivo(metodo.id);
      return;
    }

    if (!TIPOS_LOGO.includes(archivo.type)) {
      this.qrErrores.update((errores) => ({
        ...errores,
        [metodo.id]: 'El QR debe ser JPG, PNG o WEBP.',
      }));
      input.value = '';
      return;
    }

    if (archivo.size > MAX_LOGO_BYTES) {
      this.qrErrores.update((errores) => ({
        ...errores,
        [metodo.id]: 'El QR no puede superar los 2 MB.',
      }));
      input.value = '';
      return;
    }

    this.revocarQrBlob(metodo.id);
    const preview = URL.createObjectURL(archivo);
    this.qrPreviews.update((previews) => ({ ...previews, [metodo.id]: preview }));
    this.qrArchivos.update((archivos) => ({ ...archivos, [metodo.id]: archivo }));
    this.quitarQr.update((valores) => ({ ...valores, [metodo.id]: false }));
  }

  quitarQrActual(metodo: MetodoPagoConfig): void {
    this.limpiarQrArchivo(metodo.id);
    this.quitarQr.update((valores) => ({ ...valores, [metodo.id]: true }));
  }

  qrPreview(metodo: MetodoPagoConfig): string | null {
    if (this.quitarQr()[metodo.id]) {
      return null;
    }

    return this.qrPreviews()[metodo.id] ?? metodo.qr_url;
  }

  qrArchivoNombre(metodo: MetodoPagoConfig): string | null {
    return this.qrArchivos()[metodo.id]?.name ?? metodo.qr_nombre;
  }

  guardarPago(metodo: MetodoPagoConfig): void {
    if (this.guardandoPagoId() !== null) {
      return;
    }

    const formData = new FormData();
    formData.append('nombre', metodo.nombre?.trim() ?? '');
    formData.append('instrucciones', metodo.instrucciones ?? '');
    formData.append('titular', metodo.titular ?? '');
    formData.append('banco', metodo.banco ?? '');
    formData.append('numero_cuenta', metodo.numero_cuenta ?? '');
    formData.append('activo', metodo.activo ? '1' : '0');
    formData.append('orden', String(metodo.orden ?? 0));

    const qr = this.qrArchivos()[metodo.id];
    if (qr) {
      formData.append('qr', qr);
    }

    if (this.quitarQr()[metodo.id]) {
      formData.append('quitar_qr', '1');
    }

    this.guardandoPagoId.set(metodo.id);

    this.service.guardarMetodoPago(metodo.id, formData).subscribe({
      next: (res) => {
        this.guardandoPagoId.set(null);

        if (res.success && res.data) {
          const actualizado = res.data;
          this.metodosPago.update((metodos) =>
            metodos.map((item) => (item.id === actualizado.id ? actualizado : item)),
          );
          this.revocarQrBlob(metodo.id);
          this.qrArchivos.update((archivos) => {
            const copia = { ...archivos };
            delete copia[metodo.id];
            return copia;
          });
          this.qrPreviews.update((previews) => {
            const copia = { ...previews };
            delete copia[metodo.id];
            return copia;
          });
          this.quitarQr.update((valores) => ({ ...valores, [metodo.id]: false }));
          this.toast.success('Método de pago actualizado.');
          this.cargarHistorial();
        }
      },
      error: (err) => {
        this.guardandoPagoId.set(null);
        this.toast.error(err.message ?? 'No se pudo guardar el método de pago.');
      },
    });
  }

  alternarEntrega(metodo: MetodoEntregaConfig): void {
    if (this.accionandoId() !== null) {
      return;
    }

    const anterior = metodo.activo;
    metodo.activo = !anterior;
    this.accionandoId.set(metodo.id);

    this.service.actualizarMetodoEntrega(metodo.id, { activo: metodo.activo }).subscribe({
      next: (res) => {
        this.accionandoId.set(null);
        if (res.success && res.data) {
          metodo.activo = res.data.activo;
          this.toast.success(`Método de entrega ${metodo.activo ? 'activado' : 'desactivado'}.`);
        }
      },
      error: (err) => {
        this.accionandoId.set(null);
        metodo.activo = anterior;
        this.toast.error(err.message ?? 'No se pudo actualizar el método de entrega.');
      },
    });
  }

  guardarCosto(metodo: MetodoEntregaConfig): void {
    const costo = parseFloat(metodo.costo);
    if (Number.isNaN(costo) || costo < 0) {
      this.toast.error('Ingresá un costo válido.');
      return;
    }

    this.guardandoCosto.set(metodo.id);

    this.service.actualizarMetodoEntrega(metodo.id, { costo }).subscribe({
      next: (res) => {
        this.guardandoCosto.set(null);
        if (res.success && res.data) {
          metodo.costo = res.data.costo;
          this.toast.success('Costo de envío actualizado.');
        }
      },
      error: (err) => {
        this.guardandoCosto.set(null);
        this.toast.error(err.message ?? 'No se pudo actualizar el costo.');
      },
    });
  }

  onLogoSeleccionado(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0];
    this.logoError.set(null);

    if (!archivo) {
      this.logoArchivo.set(null);
      return;
    }

    if (!TIPOS_LOGO.includes(archivo.type)) {
      this.logoError.set('El logo debe ser JPG, PNG o WEBP.');
      input.value = '';
      return;
    }

    if (archivo.size > MAX_LOGO_BYTES) {
      this.logoError.set('El logo no puede superar los 2 MB.');
      input.value = '';
      return;
    }

    this.revocarLogoBlob();
    this.logoBlobUrl = URL.createObjectURL(archivo);
    this.logoPreview.set(this.logoBlobUrl);
    this.logoArchivo.set(archivo);
    this.quitarLogo.set(false);
  }

  quitarLogoActual(): void {
    this.logoArchivo.set(null);
    this.quitarLogo.set(true);
    this.revocarLogoBlob();
    this.logoPreview.set(null);
    this.logoError.set(null);

    if (this.logoInput) {
      this.logoInput.nativeElement.value = '';
    }
  }

  limpiarWhatsapp(): void {
    this.datos.whatsapp = (this.datos.whatsapp ?? '')
      .replace(/\D/g, '')
      .slice(0, 15);
  }

  guardarTienda(): void {
    const whatsapp = (this.datos.whatsapp ?? '').trim();

    if (whatsapp.length > 0 && !/^\d{8,15}$/.test(whatsapp)) {
      this.toast.error('El WhatsApp debe contener solo dígitos, incluyendo el código de país.');
      return;
    }

    const formData = new FormData();

    const campos: Array<[string, string | null]> = [
      ['nombre', this.datos.nombre],
      ['whatsapp', whatsapp],
      ['correo', this.datos.correo],
      ['direccion', this.datos.direccion],
      ['horario', this.datos.horario],
      ['instagram', this.datos.instagram],
      ['facebook', this.datos.facebook],
      ['tiktok', this.datos.tiktok],
      ['cambios_devoluciones', this.datos.textos.cambios_devoluciones],
      ['envios', this.datos.textos.envios],
      ['terminos', this.datos.textos.terminos],
      ['moneda_simbolo', this.datos.moneda.simbolo],
      ['moneda_decimales', String(this.datos.moneda.decimales ?? 2)],
      ['moneda_separador_miles', this.datos.moneda.separador_miles],
      ['moneda_separador_decimal', this.datos.moneda.separador_decimal],
    ];

    for (const [campo, valor] of campos) {
      formData.append(campo, valor ?? '');
    }

    const logo = this.logoArchivo();
    if (logo) {
      formData.append('logo', logo);
    }

    if (this.quitarLogo()) {
      formData.append('quitar_logo', '1');
    }

    this.guardandoTienda.set(true);

    this.service.actualizarTienda(formData).subscribe({
      next: (res) => {
        this.guardandoTienda.set(false);

        if (res.success && res.data) {
          this.datos = res.data;
          this.logoArchivo.set(null);
          this.quitarLogo.set(false);
          this.revocarLogoBlob();
          this.logoPreview.set(res.data.logo_url);
          this.toast.success('Datos de la tienda actualizados.');
          this.cargarHistorial();
        }
      },
      error: (err) => {
        this.guardandoTienda.set(false);
        this.toast.error(err.message ?? 'No se pudieron guardar los datos de la tienda.');
      },
    });
  }

  formatearMuestra(): string {
    const { simbolo, decimales, separador_miles, separador_decimal } = this.datos.moneda;
    const cantidad = Math.max(0, Math.min(4, Number(decimales) || 0));
    const [entero, fraccion] = (1234.5).toFixed(cantidad).split('.');
    const enteroAgrupado = separador_miles
      ? entero.replace(/\B(?=(\d{3})+(?!\d))/g, separador_miles)
      : entero;

    return `${simbolo ?? 'Bs'} ${enteroAgrupado}${fraccion ? `${separador_decimal ?? '.'}${fraccion}` : ''}`;
  }

  valorHistorial(registro: ConfiguracionHistorial): string {
    if (!registro.valor_nuevo) {
      return 'Vacío';
    }

    if (registro.campo === 'tienda_logo' || registro.campo === 'metodo_pago_qr') {
      return 'Imagen actualizada';
    }

    if (registro.campo === 'metodo_pago_activo') {
      return registro.valor_nuevo === '1' ? 'Activo' : 'Inactivo';
    }

    const valor = registro.valor_nuevo.replace(/\s+/g, ' ').trim();
    return valor.length > 80 ? `${valor.slice(0, 80)}…` : valor;
  }

  fechaHistorial(registro: ConfiguracionHistorial): string {
    if (!registro.created_at) {
      return '';
    }

    return new Date(registro.created_at).toLocaleString('es-BO', {
      dateStyle: 'short',
      timeStyle: 'short',
    });
  }

  readonly moneda = formatearMoneda;

  private limpiarQrArchivo(metodoId: number): void {
    this.revocarQrBlob(metodoId);
    this.qrArchivos.update((archivos) => {
      const copia = { ...archivos };
      delete copia[metodoId];
      return copia;
    });
    this.qrPreviews.update((previews) => {
      const copia = { ...previews };
      delete copia[metodoId];
      return copia;
    });
  }

  private revocarQrBlob(metodoId: number): void {
    const preview = this.qrPreviews()[metodoId];
    if (preview) {
      URL.revokeObjectURL(preview);
    }
  }

  private revocarQrBlobs(): void {
    for (const preview of Object.values(this.qrPreviews())) {
      URL.revokeObjectURL(preview);
    }
  }

  private datosVacios(): ConfiguracionTienda {
    return {
      nombre: null,
      logo: null,
      logo_url: null,
      whatsapp: null,
      correo: null,
      direccion: null,
      horario: null,
      instagram: null,
      facebook: null,
      tiktok: null,
      textos: {
        cambios_devoluciones: null,
        envios: null,
        terminos: null,
      },
      moneda: {
        simbolo: 'Bs',
        decimales: 2,
        separador_miles: '',
        separador_decimal: '.',
      },
    };
  }

  private revocarLogoBlob(): void {
    if (this.logoBlobUrl) {
      URL.revokeObjectURL(this.logoBlobUrl);
      this.logoBlobUrl = null;
    }
  }
}
