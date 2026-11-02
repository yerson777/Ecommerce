import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/services/auth.service';
import { EnviosService } from '../../core/services/envios.service';
import { ToastService } from '../../core/services/toast.service';
import {
  CambioEstadoEnvioPayload,
  EstadoEnvio,
  ESTADOS_ENVIO,
  ETIQUETA_ESTADO_ENVIO,
  ETIQUETA_MODALIDAD,
  ModalidadEnvio,
  ModalidadEntrega,
  TONO_ESTADO_ENVIO,
  ZonaEnvio,
  ZonaEnvioPayload,
} from '../../core/models/envio';
import { Paginated } from '../../core/models/paginated';
import { MetodoPagoRef } from '../../core/models/pago';
import { Pedido } from '../../core/models/pedido';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    EmptyStateComponent,
    ModalComponent,
    PaginatorComponent,
    SpinnerComponent,
  ],
  selector: 'app-envios',
  standalone: true,
  styleUrl: './envios.scss',
  templateUrl: './envios.html',
})
export class EnviosComponent implements OnInit {
  private readonly enviosService = inject(EnviosService);
  private readonly toast = inject(ToastService);
  private readonly auth = inject(AuthService);

  readonly esAdmin = computed(() => {
    const rol = this.auth.usuario()?.role;
    return rol === 'admin' || rol === 'super_admin';
  });

  readonly vista = signal<'lista' | 'config'>('lista');
  readonly soloPorEnviar = signal(false);
  readonly filtros = signal({
    busqueda: '',
    estado_envio: '' as EstadoEnvio | '',
    modalidad: '' as ModalidadEnvio | '',
    fecha_desde: '',
    fecha_hasta: '',
  });

  readonly resultados = signal<Paginated<Pedido> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly perPage = 15;

  readonly detalle = signal<Pedido | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);
  readonly guardando = signal(false);

  readonly metodosPago = signal<MetodoPagoRef[]>([]);

  paqueteria = '';
  numeroGuia = '';
  nota = '';
  costoReal: number | null = null;
  metodoPagoId: number | null = null;

  readonly zonas = signal<ZonaEnvio[]>([]);
  readonly modalidades = signal<ModalidadEntrega[]>([]);
  readonly configCargando = signal(false);
  readonly zonaEditando = signal<number | null>(null);

  zonaForm: ZonaEnvioPayload = this.formularioZonaVacio();

  ngOnInit(): void {
    this.cargar();
    this.cargarMetodosPago();
  }

  get transiciones(): EstadoEnvio[] {
    return this.detalle()?.envio?.transiciones ?? [];
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({
      busqueda: '',
      estado_envio: '',
      modalidad: '',
      fecha_desde: '',
      fecha_hasta: '',
    });
    this.onBuscar();
  }

  cambiarVista(vista: 'lista' | 'config'): void {
    this.vista.set(vista);
    if (vista === 'config') {
      this.cargarConfiguracion();
    }
  }

  togglePorEnviar(): void {
    this.soloPorEnviar.set(!this.soloPorEnviar());
    this.pagina.set(1);
    this.cargar();
  }

  cargar(): void {
    const f = this.filtros();
    const filtros = {
      busqueda: f.busqueda || undefined,
      estado_envio: f.estado_envio || undefined,
      modalidad: f.modalidad || undefined,
      fecha_desde: f.fecha_desde || undefined,
      fecha_hasta: f.fecha_hasta || undefined,
      page: this.pagina(),
      per_page: this.perPage,
    };

    this.cargando.set(true);
    const peticion = this.soloPorEnviar()
      ? this.enviosService.porEnviar(filtros)
      : this.enviosService.listar(filtros);

    peticion.subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.resultados.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(this.mensajeError(err, 'No se pudieron cargar los envíos.'));
      },
    });
  }

  verDetalle(pedido: Pedido): void {
    this.detalleAbierto.set(true);
    this.detalleCargando.set(true);
    this.detalle.set(null);

    this.enviosService.detalle(pedido.id).subscribe({
      next: (res) => {
        this.detalleCargando.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
          this.prepararFormularioEnvio(res.data);
        }
      },
      error: (err) => {
        this.detalleCargando.set(false);
        this.toast.error(this.mensajeError(err, 'No se pudo cargar el envío.'));
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  avanzarEstado(estado: EstadoEnvio): void {
    const pedido = this.detalle();
    if (!pedido || this.guardando()) {
      return;
    }

    const payload: CambioEstadoEnvioPayload = {
      estado,
      paqueteria: this.paqueteria || null,
      numero_guia: this.numeroGuia || null,
      nota: this.nota || null,
    };

    this.guardando.set(true);
    this.enviosService.cambiarEstado(pedido.id, payload).subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
          this.prepararFormularioEnvio(res.data);
          this.toast.success('Estado del envío actualizado.');
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando.set(false);
        this.toast.error(this.mensajeError(err, 'No se pudo actualizar el envío.'));
      },
    });
  }

  guardarDatos(): void {
    const pedido = this.detalle();
    if (!pedido || this.guardando()) {
      return;
    }

    this.guardando.set(true);
    this.enviosService
      .actualizarDatos(pedido.id, {
        paqueteria: this.paqueteria || null,
        numero_guia: this.numeroGuia || null,
        nota: this.nota || null,
      })
      .subscribe({
        next: (res) => {
          this.guardando.set(false);
          if (res.success && res.data) {
            this.detalle.set(res.data);
            this.prepararFormularioEnvio(res.data);
            this.toast.success('Datos del envío guardados.');
            this.cargar();
          }
        },
        error: (err) => {
          this.guardando.set(false);
          this.toast.error(this.mensajeError(err, 'No se pudieron guardar los datos.'));
        },
      });
  }

  guardarCostoReal(): void {
    const pedido = this.detalle();
    if (!pedido || this.guardando()) {
      return;
    }

    if (this.costoReal === null || this.costoReal < 0) {
      this.toast.error('Indica el costo real del envío.');
      return;
    }
    if (!this.metodoPagoId) {
      this.toast.error('Selecciona el método con el que se pagó el envío.');
      return;
    }

    this.guardando.set(true);
    this.enviosService
      .registrarCostoReal(pedido.id, {
        costo_real: this.costoReal,
        metodo_pago_id: this.metodoPagoId,
      })
      .subscribe({
        next: (res) => {
          this.guardando.set(false);
          if (res.success && res.data) {
            this.detalle.set(res.data);
            this.prepararFormularioEnvio(res.data);
            this.toast.success('Costo real registrado en Caja.');
            this.cargar();
          }
        },
        error: (err) => {
          this.guardando.set(false);
          this.toast.error(this.mensajeError(err, 'No se pudo registrar el costo real.'));
        },
      });
  }

  exportar(): void {
    const f = this.filtros();
    this.enviosService
      .exportar({
        busqueda: f.busqueda || undefined,
        estado_envio: f.estado_envio || undefined,
        modalidad: f.modalidad || undefined,
        fecha_desde: f.fecha_desde || undefined,
        fecha_hasta: f.fecha_hasta || undefined,
      })
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `envios_${new Date().toISOString().slice(0, 10)}.csv`;
          a.click();
          URL.revokeObjectURL(url);
        },
        error: () => this.toast.error('No se pudo exportar el listado de envíos.'),
      });
  }

  abrirWhatsApp(pedido: Pedido): void {
    const telefono = pedido.cliente?.telefono;
    if (!telefono) {
      this.toast.error('El cliente no tiene teléfono registrado.');
      return;
    }
    const limpio = telefono.replace(/[^0-9]/g, '');
    const texto = encodeURIComponent(
      `Hola ${pedido.cliente?.nombre ?? ''}, te escribimos por tu pedido ${pedido.numero_pedido}.`,
    );
    window.open(`https://wa.me/${limpio}?text=${texto}`, '_blank', 'noopener');
  }

  cargarConfiguracion(): void {
    this.configCargando.set(true);
    this.enviosService.zonas().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.zonas.set(res.data);
        }
      },
      error: (err) => this.toast.error(this.mensajeError(err, 'No se pudieron cargar las zonas.')),
    });

    this.enviosService.modalidadesConfig().subscribe({
      next: (res) => {
        this.configCargando.set(false);
        if (res.success && res.data) {
          this.modalidades.set(res.data);
        }
      },
      error: (err) => {
        this.configCargando.set(false);
        this.toast.error(this.mensajeError(err, 'No se pudieron cargar las modalidades.'));
      },
    });
  }

  editarZona(zona: ZonaEnvio): void {
    this.zonaEditando.set(zona.id);
    this.zonaForm = {
      nombre: zona.nombre,
      departamento: zona.departamento,
      costo: Number(zona.costo),
      tiempo_estimado: zona.tiempo_estimado,
      envio_gratis_desde: zona.envio_gratis_desde !== null ? Number(zona.envio_gratis_desde) : null,
      costo_por_confirmar: zona.costo_por_confirmar,
      activo: zona.activo,
      orden: zona.orden,
    };
  }

  cancelarEdicionZona(): void {
    this.zonaEditando.set(null);
    this.zonaForm = this.formularioZonaVacio();
  }

  guardarZona(): void {
    if (!this.zonaForm.nombre.trim()) {
      this.toast.error('Indica el nombre de la zona.');
      return;
    }

    const id = this.zonaEditando();
    const peticion = id
      ? this.enviosService.actualizarZona(id, this.zonaForm)
      : this.enviosService.crearZona(this.zonaForm);

    peticion.subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(id ? 'Zona actualizada.' : 'Zona creada.');
          this.cancelarEdicionZona();
          this.cargarConfiguracion();
        }
      },
      error: (err) => this.toast.error(this.mensajeError(err, 'No se pudo guardar la zona.')),
    });
  }

  toggleZona(zona: ZonaEnvio): void {
    this.enviosService.cambiarEstadoZona(zona.id, !zona.activo).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.zonas.update((zonas) =>
            zonas.map((item) => (item.id === zona.id ? res.data! : item)),
          );
        }
      },
      error: (err) => this.toast.error(this.mensajeError(err, 'No se pudo cambiar el estado.')),
    });
  }

  cambiarModalidad(metodo: ModalidadEntrega, modalidad: ModalidadEnvio): void {
    this.enviosService.actualizarModalidad(metodo.id, { modalidad }).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.modalidades.update((items) =>
            items.map((item) => (item.id === metodo.id ? { ...item, ...res.data! } : item)),
          );
          this.toast.success('Modalidad actualizada.');
        }
      },
      error: (err) => this.toast.error(this.mensajeError(err, 'No se pudo actualizar la modalidad.')),
    });
  }

  toggleModalidadActiva(metodo: ModalidadEntrega): void {
    this.enviosService
      .actualizarModalidad(metodo.id, { modalidad: metodo.modalidad, activo: !metodo.activo })
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.modalidades.update((items) =>
              items.map((item) => (item.id === metodo.id ? { ...item, ...res.data! } : item)),
            );
          }
        },
        error: (err) => this.toast.error(this.mensajeError(err, 'No se pudo cambiar el estado.')),
      });
  }

  cambiarCostoModalidad(metodo: ModalidadEntrega, valor: string): void {
    const costo = Number(valor);
    if (Number.isNaN(costo) || costo < 0) {
      return;
    }

    this.enviosService
      .actualizarModalidad(metodo.id, { modalidad: metodo.modalidad, costo })
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.modalidades.update((items) =>
              items.map((item) => (item.id === metodo.id ? { ...item, ...res.data! } : item)),
            );
          }
        },
        error: (err) => this.toast.error(this.mensajeError(err, 'No se pudo cambiar el costo.')),
      });
  }

  etiquetaEstado(estado: EstadoEnvio): string {
    return ETIQUETA_ESTADO_ENVIO[estado] ?? estado;
  }

  tonoEstado(estado: EstadoEnvio): string {
    return TONO_ESTADO_ENVIO[estado] ?? 'neutral';
  }

  etiquetaModalidad(modalidad: ModalidadEnvio | null | undefined): string {
    return modalidad ? ETIQUETA_MODALIDAD[modalidad] : 'Sin modalidad';
  }

  estados(): EstadoEnvio[] {
    return ESTADOS_ENVIO;
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = Number(valor ?? 0);
    return `Bs ${numero.toFixed(2)}`;
  }

  fecha(valor: string | null | undefined): string {
    if (!valor) {
      return '—';
    }
    const fecha = new Date(valor);
    return Number.isNaN(fecha.getTime()) ? '—' : fecha.toLocaleDateString('es-BO');
  }

  zonaPrincipal(pedido: Pedido): string {
    return pedido.zona_envio?.nombre ?? pedido.cliente?.ciudad ?? '—';
  }

  private cargarMetodosPago(): void {
    this.enviosService.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosPago.set(res.data);
          if (!this.metodoPagoId && res.data.length > 0) {
            this.metodoPagoId = res.data[0].id;
          }
        }
      },
      error: () => undefined,
    });
  }

  private prepararFormularioEnvio(pedido: Pedido): void {
    const envio = pedido.envio;
    this.paqueteria = envio?.paqueteria ?? '';
    this.numeroGuia = envio?.numero_guia ?? '';
    this.nota = envio?.nota ?? '';
    this.costoReal = envio?.costo_real !== null && envio?.costo_real !== undefined
      ? Number(envio.costo_real)
      : null;

    if (!this.metodoPagoId && this.metodosPago().length > 0) {
      this.metodoPagoId = this.metodosPago()[0].id;
    }
  }

  private formularioZonaVacio(): ZonaEnvioPayload {
    return {
      nombre: '',
      departamento: null,
      costo: 0,
      tiempo_estimado: null,
      envio_gratis_desde: null,
      costo_por_confirmar: false,
      activo: true,
      orden: 0,
    };
  }

  private mensajeError(err: unknown, porDefecto: string): string {
    const error = err as {
      message?: string;
      error?: { message?: string; errors?: Record<string, string[]> };
    };
    const errores = error?.error?.errors;
    if (errores) {
      const primero = Object.values(errores)[0];
      if (Array.isArray(primero) && primero.length > 0) {
        return primero[0];
      }
    }
    return error?.error?.message ?? error?.message ?? porDefecto;
  }
}
