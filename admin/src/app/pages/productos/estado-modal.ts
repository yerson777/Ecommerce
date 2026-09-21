import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Producto } from '../../core/models/producto';
import { ClientesService } from '../../core/services/clientes.service';
import { InventarioService } from '../../core/services/inventario.service';
import { ToastService } from '../../core/services/toast.service';
import { ModalComponent } from '../../shared/components/modal/modal';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [FormsModule, ModalComponent, SpinnerComponent],
  selector: 'app-estado-modal',
  standalone: true,
  styleUrl: './estado-modal.scss',
  templateUrl: './estado-modal.html',
})
export class EstadoModalComponent implements OnInit {
  readonly producto = input.required<Producto>();
  readonly tipo = input<'reservar' | 'vender'>('reservar');

  readonly realizado = output<void>();
  readonly cerrado = output<void>();

  private readonly inventarioService = inject(InventarioService);
  private readonly clientesService = inject(ClientesService);
  private readonly toast = inject(ToastService);

  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly cargandoClientes = signal(false);
  readonly clientes = signal<{ id: number; nombre: string }[]>([]);

  readonly venceEn = signal('');
  readonly clienteId = signal<number | null>(null);
  readonly costoEnvio = signal<number>(0);
  readonly notas = signal('');

  ngOnInit(): void {
    if (this.tipo() === 'vender') {
      this.cargarClientes();
    }
  }

  esReservar(): boolean {
    return this.tipo() === 'reservar';
  }

  esVender(): boolean {
    return this.tipo() === 'vender';
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  private cargarClientes(): void {
    this.cargandoClientes.set(true);

    this.clientesService.opciones().subscribe({
      next: (res) => {
        this.cargandoClientes.set(false);
        if (res.success && res.data) {
          this.clientes.set(res.data);
        }
      },
      error: (err) => {
        this.cargandoClientes.set(false);
        this.errorBanner.set(err.message ?? 'No se pudieron cargar los clientes.');
      },
    });
  }

  confirmar(): void {
    const producto = this.producto();

    if (this.esVender()) {
      if (!this.clienteId()) {
        this.errorBanner.set('Selecciona un cliente.');
        return;
      }

      this.guardando.set(true);
      this.errorBanner.set(null);

      this.inventarioService
        .vender({
          producto_id: producto.id,
          cliente_id: this.clienteId() as number,
          costo_envio: Number(this.costoEnvio()) || 0,
          notas: this.notas().trim() || null,
        })
        .subscribe({
          next: (res) => {
            this.guardando.set(false);
            if (res.success) {
              this.toast.success(res.message ?? 'Venta registrada.');
              this.realizado.emit();
            }
          },
          error: (err) => {
            this.guardando.set(false);
            this.errorBanner.set(err.message ?? 'No se pudo registrar la venta.');
          },
        });

      return;
    }

    this.guardando.set(true);
    this.errorBanner.set(null);

    this.inventarioService
      .reservar(
        producto.id,
        this.venceEn()
          ? new Date(this.venceEn()).toISOString().slice(0, 19).replace('T', ' ')
          : undefined,
      )
      .subscribe({
        next: (res) => {
          this.guardando.set(false);
          if (res.success) {
            this.toast.success(res.message ?? 'Prenda reservada.');
            this.realizado.emit();
          }
        },
        error: (err) => {
          this.guardando.set(false);
          this.errorBanner.set(err.message ?? 'No se pudo reservar la prenda.');
        },
      });
  }
}