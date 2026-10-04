import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { MetodoPagoRef } from '../../core/models/pago';
import { MetodoEntregaConfig } from '../../core/models/configuracion';
import { Producto } from '../../core/models/producto';
import { Venta, VentaPresencialPayload, ClienteOpcion } from '../../core/models/venta';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { VentasService } from '../../core/services/ventas.service';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [FormsModule, ModalComponent, EmptyStateComponent, SpinnerComponent],
  selector: 'app-venta-presencial-modal',
  standalone: true,
  styleUrl: './venta-presencial-modal.scss',
  templateUrl: './venta-presencial-modal.html',
})
export class VentaPresencialModalComponent implements OnInit {
  readonly clientes = input<ClienteOpcion[]>([]);
  readonly metodos = input<MetodoPagoRef[]>([]);
  readonly metodosEntrega = input<MetodoEntregaConfig[]>([]);

  readonly registrada = output<Venta>();
  readonly cerrado = output<void>();

  private readonly productosService = inject(ProductosService);
  private readonly ventasService = inject(VentasService);
  private readonly toast = inject(ToastService);

  busqueda = '';
  readonly disponibles = signal<Producto[]>([]);
  readonly seleccionadas = signal<Producto[]>([]);
  readonly buscando = signal(false);
  readonly busquedaHecha = signal(false);

  clienteId: number | null = null;
  entregaId: number | null = null;
  metodoId: number | null = null;

  cupon = '';
  descuento: number | null = null;
  montoPagado: number | null = null;
  referencia = '';
  notas = '';
  comprobante: File | null = null;
  comprobanteNombre = '';

  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  ngOnInit(): void {
    const entregas = this.metodosEntrega();
    const costo0 = entregas.find((e) => e.activo && Number(e.costo) === 0);
    this.entregaId = costo0?.id ?? entregas.find((e) => e.activo)?.id ?? entregas[0]?.id ?? null;

    const activos = this.metodos().filter((m) => m.activo);
    this.metodoId = activos[0]?.id ?? null;

    this.buscarPrendas();
  }

  buscarPrendas(): void {
    this.buscando.set(true);
    this.busquedaHecha.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    this.productosService
      .listar({ busqueda: this.busqueda.trim() || undefined, estado: 'disponible', page: 1, per_page: 100 })
      .subscribe({
        next: (res) => {
          this.buscando.set(false);
          if (res.success && res.data) {
            const seleccionadas = this.seleccionadas();
            this.disponibles.set(res.data.data.filter((p) => !seleccionadas.some((s) => s.id === p.id)));
          }
        },
        error: (err: ApiError) => {
          this.buscando.set(false);
          this.errorBanner.set(err.message ?? 'No se pudieron cargar las prendas disponibles.');
        },
      });
  }

  agregar(producto: Producto): void {
    this.seleccionadas.update((prev) => [...prev, producto]);
    this.disponibles.update((prev) => prev.filter((p) => p.id !== producto.id));
    this.errorBanner.set(null);
    this.errores.set(null);
  }

  quitar(producto: Producto): void {
    this.seleccionadas.update((prev) => prev.filter((p) => p.id !== producto.id));
    this.disponibles.update((prev) => [producto, ...prev]);
  }

  onCuponCambio(): void {
    if (this.cupon.trim()) {
      this.descuento = null;
    }
  }

  onDescuentoCambio(): void {
    if (this.descuento != null && this.descuento > 0) {
      this.cupon = '';
    }
  }

  subtotal(): number {
    return this.seleccionadas().reduce((acc, p) => acc + Number(p.precio), 0);
  }

  costoEnvio(): number {
    const entrega = this.metodosEntrega().find((e) => e.id === this.entregaId);
    return entrega ? Number(entrega.costo) : 0;
  }

  descuentoEfectivo(): number {
    if (this.cupon.trim()) {
      return 0;
    }
    return this.descuento != null && this.descuento > 0 ? Math.min(this.descuento, this.subtotal()) : 0;
  }

  totalEstimado(): number {
    return Math.max(0, this.subtotal() - this.descuentoEfectivo()) + this.costoEnvio();
  }

  cobrarTotal(): void {
    this.montoPagado = Math.round(this.totalEstimado() * 100) / 100;
  }

  onComprobante(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    input.value = '';

    if (!archivo) {
      return;
    }

    if (!this.comprobanteValido(archivo)) {
      this.toast.error('El comprobante debe ser una imagen (JPG, PNG o WebP) de hasta 5 MB.');
      return;
    }

    this.comprobante = archivo;
    this.comprobanteNombre = archivo.name;
  }

  quitarComprobante(): void {
    this.comprobante = null;
    this.comprobanteNombre = '';
  }

  guardar(): void {
    const errores: Record<string, string[]> = {};

    if (this.clienteId == null) {
      errores['cliente_id'] = ['Selecciona el cliente de la venta.'];
    }
    if (this.seleccionadas().length === 0) {
      errores['productos'] = ['Incluye al menos una prenda.'];
    }
    if (this.entregaId == null) {
      errores['metodo_entrega_id'] = ['Selecciona el método de entrega.'];
    }
    if (this.metodoId == null) {
      errores['metodo_pago_id'] = ['Selecciona el método de pago.'];
    }
    if (this.montoPagado != null && this.montoPagado < 0) {
      errores['monto_pagado'] = ['El monto pagado no puede ser negativo.'];
    }
    if (this.cupon.trim() && this.descuento != null && this.descuento > 0) {
      errores['descuento'] = ['No puedes combinar cupón y descuento manual.'];
      errores['cupon_codigo'] = ['No puedes combinar cupón y descuento manual.'];
    }

    if (Object.keys(errores).length > 0) {
      this.errorBanner.set('Revisa los campos marcados.');
      this.errores.set(errores);
      return;
    }

    const payload: VentaPresencialPayload = {
      cliente_id: this.clienteId as number,
      productos: this.seleccionadas().map((p) => p.id),
      metodo_entrega_id: this.entregaId as number,
      metodo_pago_id: this.metodoId as number,
      cupon_codigo: this.cupon.trim() || null,
      descuento: this.descuento,
      monto_pagado: this.montoPagado,
      referencia: this.referencia.trim() || null,
      notas: this.notas.trim() || null,
    };

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    this.ventasService.crear(payload, this.comprobante).subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success('Venta presencial registrada.');
          this.registrada.emit(res.data);
        }
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorBanner.set(err.message ?? 'No se pudo registrar la venta.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  nombreCategoria(producto: Producto): string {
    return producto.categoria?.nombre ?? '';
  }

  private comprobanteValido(archivo: File): boolean {
    const permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    return permitidos.includes(archivo.type) && archivo.size <= 5 * 1024 * 1024;
  }
}