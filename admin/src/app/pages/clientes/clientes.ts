import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Cliente } from '../../core/models/cliente';
import { Paginated } from '../../core/models/paginated';
import { ETIQUETA_ESTADO_PEDIDO, TONO_ESTADO_PEDIDO, EstadoPedido } from '../../core/models/pedido';
import { ClientesService } from '../../core/services/clientes.service';
import { ToastService } from '../../core/services/toast.service';
import { enlaceWhatsApp } from '../../core/utils/whatsapp';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

function esEstadoPedido(estado: string): estado is EstadoPedido {
  return estado in ETIQUETA_ESTADO_PEDIDO;
}

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    EmptyStateComponent,
    ModalComponent,
    NotificationCenterComponent,
    PaginatorComponent,
    SpinnerComponent,
  ],
  selector: 'app-clientes',
  standalone: true,
  styleUrl: './clientes.scss',
  templateUrl: './clientes.html',
})
export class ClientesComponent implements OnInit {
  private readonly clientesService = inject(ClientesService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({ busqueda: '' });

  readonly resultados = signal<Paginated<Cliente> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly detalle = signal<Cliente | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);

  readonly perPage = 15;

  ngOnInit(): void {
    this.cargar();
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.clientesService
      .listar({
        busqueda: f.busqueda || undefined,
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
          this.toast.error(err.message ?? 'No se pudieron cargar los clientes.');
        },
      });
  }

  verDetalle(cliente: Cliente): void {
    this.detalleAbierto.set(true);
    this.detalleCargando.set(true);
    this.detalle.set(null);

    this.clientesService.detalle(cliente.id).subscribe({
      next: (res) => {
        this.detalleCargando.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
        }
      },
      error: (err) => {
        this.detalleCargando.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar el detalle.');
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  etiquetaEstado(estado: string): string {
    return esEstadoPedido(estado) ? ETIQUETA_ESTADO_PEDIDO[estado] : estado;
  }

  tonoEstado(estado: string): string {
    return esEstadoPedido(estado) ? TONO_ESTADO_PEDIDO[estado] : 'neutral';
  }

  whatsapp(cliente: Cliente, mensaje?: string): string {
    return enlaceWhatsApp(cliente.telefono, mensaje);
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }
}