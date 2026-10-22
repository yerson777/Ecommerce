import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ClienteDuplicadoRef } from '../../core/models/cliente';
import { ClientesService } from '../../core/services/clientes.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ModalComponent } from '../../shared/components/modal/modal';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface SeleccionGrupo {
  principal: number;
  uniendo: boolean;
}

@Component({
  imports: [FormsModule, BadgeComponent, ModalComponent, SpinnerComponent],
  selector: 'app-clientes-duplicados-modal',
  standalone: true,
  styleUrl: './clientes-duplicados-modal.scss',
  templateUrl: './clientes-duplicados-modal.html',
})
export class ClientesDuplicadosModalComponent implements OnInit {
  readonly grupos = input<ClienteDuplicadoRef[][]>([]);
  readonly cargando = input(false);

  readonly unido = output<void>();
  readonly cerrado = output<void>();

  private readonly clientesService = inject(ClientesService);
  private readonly toast = inject(ToastService);

  readonly seleccion = signal<SeleccionGrupo[]>([]);

  ngOnInit(): void {
    this.seleccion.set(this.grupos().map((grupo) => ({ principal: this.principalSugerido(grupo), uniendo: false })));
  }

  private principalSugerido(grupo: ClienteDuplicadoRef[]): number {
    const ordenado = [...grupo].sort((a, b) => {
      if (b.pedidos_count !== a.pedidos_count) {
        return b.pedidos_count - a.pedidos_count;
      }
      const fa = a.fecha_ultimo_pedido ?? '';
      const fb = b.fecha_ultimo_pedido ?? '';
      return fb.localeCompare(fa);
    });

    return ordenado[0]?.id ?? 0;
  }

  esPrincipal(grupoIndex: number, id: number): boolean {
    return this.seleccion()[grupoIndex]?.principal === id;
  }

  elegirPrincipal(grupoIndex: number, id: number): void {
    this.seleccion.update((lista) => {
      const copia = [...lista];
      copia[grupoIndex] = { ...copia[grupoIndex], principal: id };
      return copia;
    });
  }

  uniendo(grupoIndex: number): boolean {
    return this.seleccion()[grupoIndex]?.uniendo ?? false;
  }

  unir(grupoIndex: number): void {
    const grupo = this.grupos()[grupoIndex];
    const principalId = this.seleccion()[grupoIndex]?.principal;

    if (!grupo || !principalId) {
      return;
    }

    const duplicadoIds = grupo.filter((c) => c.id !== principalId).map((c) => c.id);

    if (duplicadoIds.length === 0) {
      return;
    }

    this.marcarUniendo(grupoIndex, true);

    this.clientesService.unir({ principal_id: principalId, duplicado_ids: duplicadoIds }).subscribe({
      next: (res) => {
        this.marcarUniendo(grupoIndex, false);
        if (res.success) {
          this.toast.success('Clientes unidos correctamente.');
          this.unido.emit();
        }
      },
      error: (err) => {
        this.marcarUniendo(grupoIndex, false);
        this.toast.error(err.message ?? 'No se pudieron unir los clientes.');
      },
    });
  }

  private marcarUniendo(grupoIndex: number, valor: boolean): void {
    this.seleccion.update((lista) => {
      const copia = [...lista];
      copia[grupoIndex] = { ...copia[grupoIndex], uniendo: valor };
      return copia;
    });
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }

  cerrar(): void {
    this.cerrado.emit();
  }
}
