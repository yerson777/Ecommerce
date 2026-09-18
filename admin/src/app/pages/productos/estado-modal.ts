import { Component, inject, input, OnInit, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Cliente } from '../../core/models/cliente';
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

  guardando = false;
  errorBanner: string | null = null;

  venceEn = '';
  clienteId: number | null = null;
  costoEnvio = 0;
  notas = '';

  clientes: Cliente[] = [];
  cargandoClientes = false;

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

  private cargarClientes(): void {
    this.cargandoClientes = true;

    this.clientesService.listar({ per_page: 200, page: 1 }).subscribe({
      next: (res) => {
        this.cargandoClientes = false;
        if (res.success && res.data) {
          this.clientes = res.data.data;
        }
      },
      error: (err) => {
        this.cargandoClientes = false;
        this.errorBanner = err.message ?? 'No se pudieron cargar los clientes.';
      },
    });
  }

  confirmar(): void {
    const producto = this.producto();

    if (this.esVender()) {
      if (!this.clienteId) {
        this.errorBanner = 'Selecciona un cliente.';
        return;
      }

      this.guardando = true;
      this.errorBanner = null;

      this.inventarioService
        .vender({
          producto_id: producto.id,
          cliente_id: this.clienteId,
          costo_envio: Number(this.costoEnvio) || 0,
          notas: this.notas.trim() || null,
        })
        .subscribe({
          next: (res) => {
            this.guardando = false;
            if (res.success) {
              this.toast.success(res.message ?? 'Venta registrada.');
              this.realizado.emit();
            }
          },
          error: (err) => {
            this.guardando = false;
            this.errorBanner = err.message ?? 'No se pudo registrar la venta.';
          },
        });

      return;
    }

    this.guardando = true;
    this.errorBanner = null;

    this.inventarioService
      .reservar(producto.id, this.venceEn ? new Date(this.venceEn).toISOString().slice(0, 19).replace('T', ' ') : undefined)
      .subscribe({
        next: (res) => {
          this.guardando = false;
          if (res.success) {
            this.toast.success(res.message ?? 'Prenda reservada.');
            this.realizado.emit();
          }
        },
        error: (err) => {
          this.guardando = false;
          this.errorBanner = err.message ?? 'No se pudo reservar la prenda.';
        },
      });
  }
}