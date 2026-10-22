import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { Paginated } from '../../core/models/paginated';
import {
  Devolucion,
  DevolucionItemPayload,
  ETIQUETA_ESTADO_DEVOLUCION,
  MOTIVOS_DEVOLUCION,
  TONO_ESTADO_DEVOLUCION,
  TipoDevolucion,
  VentaDisponible,
} from '../../core/models/devolucion';
import { DevolucionesService } from '../../core/services/devoluciones.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface PrendaSeleccion {
  producto_id: number;
  codigo: string | null;
  nombre: string | null;
  precio_unitario: string;
  seleccionada: boolean;
  reingresa: boolean;
}

interface RegistrarEstado {
  abierto: boolean;
  guardando: boolean;
  error: string | null;
  busqueda: string;
  ventas: VentaDisponible[];
  cargandoVentas: boolean;
  ventaId: number | null;
  motivo: string;
  monto: number | null;
  tipo: TipoDevolucion;
  motivoCategoria: string;
  nota: string;
  reembolsoMetodoId: number | null;
  prendas: PrendaSeleccion[];
  cargandoPrendas: boolean;
}

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    ConfirmDialogComponent,
    EmptyStateComponent,
    ModalComponent,
    NotificationCenterComponent,
    PaginatorComponent,
    SpinnerComponent,
  ],
  selector: 'app-devoluciones',
  standalone: true,
  styleUrl: './devoluciones.scss',
  templateUrl: './devoluciones.html',
})
export class DevolucionesComponent implements OnInit {
  private readonly devoluciones = inject(DevolucionesService);
  private readonly toast = inject(ToastService);
  private readonly route = inject(ActivatedRoute);

  readonly filtros = signal({
    busqueda: '',
    estado: '' as Devolucion['estado'] | '',
    tipo: '' as TipoDevolucion | '',
    fechaDesde: '',
    fechaHasta: '',
  });

  readonly resultados = signal<Paginated<Devolucion> | null>(null);
  readonly cargando = signal(true);

  readonly metodosPago = signal<{ id: number; nombre: string }[]>([]);

  readonly motivos = MOTIVOS_DEVOLUCION;

  readonly registrar = signal<RegistrarEstado>(this.registrarInicial());

  readonly aprobarObjetivo = signal<Devolucion | null>(null);
  readonly aprobando = signal(false);

  readonly rechazarObjetivo = signal<Devolucion | null>(null);
  readonly rechazando = signal(false);
  readonly rechazoMotivo = signal('');

  readonly reembolsarObjetivo = signal<Devolucion | null>(null);
  readonly reembolsando = signal(false);
  readonly reembolsoMetodoId = signal<number | null>(null);

  readonly imprimiendo = signal<Devolucion | null>(null);
  readonly detalle = signal<Devolucion | null>(null);

  readonly pagina = signal(1);
  readonly perPage = 15;

  private ventaInicial: number | null = null;

  ngOnInit(): void {
    this.cargarMetodosPago();

    const venta = Number(this.route.snapshot.queryParamMap.get('venta'));
    if (Number.isFinite(venta) && venta > 0) {
      this.ventaInicial = venta;
      this.abrirRegistrar();
    }

    this.cargar();
  }

  private registrarInicial(): RegistrarEstado {
    return {
      abierto: false,
      guardando: false,
      error: null,
      busqueda: '',
      ventas: [],
      cargandoVentas: false,
      ventaId: null,
      motivo: '',
      monto: null,
      tipo: 'total',
      motivoCategoria: '',
      nota: '',
      reembolsoMetodoId: null,
      prendas: [],
      cargandoPrendas: false,
    };
  }

  private cargarMetodosPago(): void {
    this.devoluciones.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosPago.set(res.data);
        }
      },
    });
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', estado: '', tipo: '', fechaDesde: '', fechaHasta: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.devoluciones
      .listar({
        busqueda: f.busqueda || undefined,
        estado: f.estado || undefined,
        tipo: f.tipo || undefined,
        fecha_desde: f.fechaDesde || undefined,
        fecha_hasta: f.fechaHasta || undefined,
        page: this.pagina(),
        per_page: this.perPage,
      })
      .subscribe({
        next: (res) => {
          this.cargando.set(false);
          if (res.success && res.data) {
            this.resultados.set(res.data);
          }
        },
        error: (err) => {
          this.cargando.set(false);
          this.toast.error(err.message ?? 'No se pudieron cargar las devoluciones.');
        },
      });
  }

  abrirRegistrar(): void {
    this.registrar.set({ ...this.registrarInicial(), abierto: true });
    this.cargarVentas();
  }

  cerrarRegistrar(): void {
    if (this.registrar().guardando) {
      return;
    }
    this.registrar.set({ ...this.registrar(), abierto: false });
  }

  buscarVentas(): void {
    this.cargarVentas();
  }

  private cargarVentas(): void {
    this.registrar.set({ ...this.registrar(), cargandoVentas: true });

    this.devoluciones.ventasDisponibles(this.registrar().busqueda.trim() || undefined).subscribe({
      next: (res) => {
        const ventas = res.success && res.data?.data ? res.data.data : [];
        const objetivo = this.ventaInicial;
        const preselegida = objetivo ? ventas.find((v) => v.id === objetivo) : undefined;
        this.registrar.set({
          ...this.registrar(),
          ventas,
          cargandoVentas: false,
          ventaId: preselegida ? preselegida.id : null,
          monto: preselegida ? parseFloat(String(preselegida.total)) || 0 : null,
        });
        this.ventaInicial = null;
        if (preselegida) {
          this.cargarPrendas();
        }
      },
      error: (err) => {
        this.registrar.set({ ...this.registrar(), cargandoVentas: false, error: err.message ?? 'No se pudieron cargar las ventas.' });
      },
    });
  }

  onSeleccionarVenta(): void {
    const venta = this.registrar().ventas.find((v) => v.id === this.registrar().ventaId);
    const monto = venta ? parseFloat(String(venta.total)) || 0 : null;
    this.registrar.set({ ...this.registrar(), monto, prendas: [] });

    if (venta) {
      this.cargarPrendas();
    }
  }

  private cargarPrendas(): void {
    const ventaId = this.registrar().ventaId;
    if (!ventaId) {
      return;
    }

    this.registrar.set({ ...this.registrar(), cargandoPrendas: true, prendas: [] });

    this.devoluciones.prendasDeVenta(ventaId).subscribe({
      next: (res) => {
        const prendas: PrendaSeleccion[] = (res.data ?? []).map((prenda) => ({
          producto_id: prenda.producto_id,
          codigo: prenda.codigo ?? null,
          nombre: prenda.nombre ?? null,
          precio_unitario: prenda.precio_unitario,
          seleccionada: true,
          reingresa: true,
        }));
        this.registrar.set({ ...this.registrar(), prendas, cargandoPrendas: false });
      },
      error: () => {
        this.registrar.set({ ...this.registrar(), cargandoPrendas: false });
      },
    });
  }

  onCambiarTipo(tipo: TipoDevolucion): void {
    this.registrar.set({ ...this.registrar(), tipo });
  }

  togglePrenda(indice: number): void {
    const prendas = this.registrar().prendas.map((prenda, i) =>
      i === indice ? { ...prenda, seleccionada: !prenda.seleccionada } : prenda,
    );
    this.registrar.set({ ...this.registrar(), prendas });
  }

  toggleReingresa(indice: number): void {
    const prendas = this.registrar().prendas.map((prenda, i) =>
      i === indice ? { ...prenda, reingresa: !prenda.reingresa } : prenda,
    );
    this.registrar.set({ ...this.registrar(), prendas });
  }

  prendasSeleccionadas(): PrendaSeleccion[] {
    return this.registrar().prendas.filter((prenda) => prenda.seleccionada);
  }

  cambiarMonto(valor: string): void {
    this.registrar.set({
      ...this.registrar(),
      monto: valor === '' ? null : Number(valor),
    });
  }

  totalVentaDeVentaSeleccionada(): string {
    const venta = this.registrar().ventas.find((v) => v.id === this.registrar().ventaId);
    return venta?.total ?? '0';
  }

  registrarDevolucion(): void {
    const estado = this.registrar();

    if (!estado.ventaId) {
      this.registrar.set({ ...estado, error: 'Selecciona la venta a devolver.' });
      return;
    }
    if (!estado.motivo.trim()) {
      this.registrar.set({ ...estado, error: 'Indica el motivo de la devolución.' });
      return;
    }

    let items: DevolucionItemPayload[] | null = null;
    if (estado.tipo === 'parcial') {
      const seleccionadas = this.prendasSeleccionadas();
      if (seleccionadas.length === 0) {
        this.registrar.set({ ...estado, error: 'Selecciona al menos una prenda para la devolución parcial.' });
        return;
      }
      items = seleccionadas.map((prenda) => ({
        producto_id: prenda.producto_id,
        reingresa: prenda.reingresa,
      }));
    }

    this.registrar.set({ ...estado, guardando: true, error: null });

    this.devoluciones
      .crear({
        venta_id: estado.ventaId,
        motivo: estado.motivo.trim(),
        monto_reembolso: estado.monto ? Number(estado.monto) : null,
        tipo: estado.tipo,
        motivo_categoria: estado.motivoCategoria || null,
        nota: estado.nota.trim() || null,
        reembolso_metodo_id: estado.reembolsoMetodoId,
        items,
      })
      .subscribe({
        next: (res) => {
          this.registrar.set({ ...this.registrar(), guardando: false });
          if (res.success) {
            this.toast.success(res.message ?? 'Devolución registrada.');
            this.registrar.set({ ...this.registrar(), abierto: false });
            this.pagina.set(1);
            this.cargar();
          }
        },
        error: (err) => {
          this.registrar.set({ ...this.registrar(), guardando: false, error: err.message ?? 'No se pudo registrar la devolución.' });
        },
      });
  }

  solicitarAprobar(devolucion: Devolucion): void {
    if (devolucion.estado === 'pendiente') {
      this.aprobarObjetivo.set(devolucion);
    }
  }

  cancelarAprobar(): void {
    this.aprobarObjetivo.set(null);
  }

  confirmarAprobar(): void {
    const devolucion = this.aprobarObjetivo();
    if (!devolucion || this.aprobando()) {
      return;
    }

    this.aprobando.set(true);

    this.devoluciones.aprobar(devolucion.id).subscribe({
      next: (res) => {
        this.aprobando.set(false);
        this.aprobarObjetivo.set(null);
        if (res.success) {
          this.toast.success(res.message ?? 'Devolución aprobada.');
          this.cargar();
        }
      },
      error: (err) => {
        this.aprobando.set(false);
        this.aprobarObjetivo.set(null);
        this.toast.error(err.message ?? 'No se pudo aprobar la devolución.');
      },
    });
  }

  solicitarRechazar(devolucion: Devolucion): void {
    this.rechazoMotivo.set('');
    this.rechazarObjetivo.set(devolucion);
  }

  cancelarRechazar(): void {
    this.rechazarObjetivo.set(null);
  }

  confirmarRechazar(): void {
    const devolucion = this.rechazarObjetivo();
    if (!devolucion || this.rechazando()) {
      return;
    }

    this.rechazando.set(true);

    this.devoluciones.rechazar(devolucion.id, this.rechazoMotivo().trim() || null).subscribe({
      next: (res) => {
        this.rechazando.set(false);
        this.rechazarObjetivo.set(null);
        if (res.success) {
          this.toast.success(res.message ?? 'Devolución rechazada.');
          this.cargar();
        }
      },
      error: (err) => {
        this.rechazando.set(false);
        this.toast.error(err.message ?? 'No se pudo rechazar la devolución.');
      },
    });
  }

  solicitarReembolsar(devolucion: Devolucion): void {
    this.reembolsoMetodoId.set(devolucion.reembolso_metodo_id ?? null);
    this.reembolsarObjetivo.set(devolucion);
  }

  cancelarReembolsar(): void {
    this.reembolsarObjetivo.set(null);
  }

  confirmarReembolsar(): void {
    const devolucion = this.reembolsarObjetivo();
    if (!devolucion || this.reembolsando()) {
      return;
    }

    this.reembolsando.set(true);

    this.devoluciones.reembolsar(devolucion.id, this.reembolsoMetodoId()).subscribe({
      next: (res) => {
        this.reembolsando.set(false);
        this.reembolsarObjetivo.set(null);
        if (res.success) {
          this.toast.success(res.message ?? 'Devolución reembolsada.');
          this.cargar();
        }
      },
      error: (err) => {
        this.reembolsando.set(false);
        this.toast.error(err.message ?? 'No se pudo reembolsar la devolución.');
      },
    });
  }

  verDetalle(devolucion: Devolucion): void {
    this.detalle.set(devolucion);
  }

  cerrarDetalle(): void {
    this.detalle.set(null);
  }

  imprimir(devolucion: Devolucion): void {
    this.imprimiendo.set(devolucion);
    setTimeout(() => {
      document.body.classList.add('printeando-comprobante');
      window.print();
      document.body.classList.remove('printeando-comprobante');
      this.imprimiendo.set(null);
    }, 60);
  }

  puedeAprobar(devolucion: Devolucion): boolean {
    return devolucion.estado === 'pendiente';
  }

  puedeReembolsar(devolucion: Devolucion): boolean {
    return devolucion.estado === 'aprobada';
  }

  etiquetaEstado(devolucion: Devolucion): string {
    return ETIQUETA_ESTADO_DEVOLUCION[devolucion.estado] ?? devolucion.estado;
  }

  tonoEstado(devolucion: Devolucion): string {
    return TONO_ESTADO_DEVOLUCION[devolucion.estado] ?? 'neutral';
  }

  etiquetaTipo(devolucion: Devolucion): string {
    return devolucion.tipo === 'parcial' ? 'Parcial' : 'Total';
  }

  etiquetaMotivo(categoria: string | null | undefined): string {
    if (!categoria) {
      return '—';
    }
    return this.motivos.find((m) => m.valor === categoria)?.etiqueta ?? categoria;
  }

  nombreCliente(devolucion: Devolucion): string {
    return devolucion.pedido?.cliente?.nombre ?? '—';
  }

  numeroVenta(devolucion: Devolucion): string {
    return devolucion.venta?.numero_venta ?? '—';
  }

  totalVenta(devolucion: Devolucion): string | null {
    return devolucion.venta?.total ?? null;
  }

  numeroPedido(devolucion: Devolucion): string {
    return devolucion.pedido?.numero_pedido ?? '—';
  }

  resumenPrendas(devolucion: Devolucion): string {
    const prendas = devolucion.prendas ?? [];
    if (prendas.length === 0) {
      return '—';
    }
    if (prendas.length === 1) {
      const prenda = prendas[0];
      return prenda.nombre ?? prenda.codigo ?? `#${prenda.producto_id}`;
    }
    return `${prendas.length} prendas`;
  }

  nombrePrenda(prenda: { nombre?: string | null; codigo?: string | null; producto_id: number }): string {
    return prenda.nombre ?? prenda.codigo ?? `#${prenda.producto_id}`;
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const fechaObj = new Date(fecha);
    if (Number.isNaN(fechaObj.getTime())) {
      return '—';
    }
    return fechaObj.toLocaleDateString('es-AR');
  }
}
