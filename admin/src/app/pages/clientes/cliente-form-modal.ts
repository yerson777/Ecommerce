import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Cliente, ClientePayload } from '../../core/models/cliente';
import { ClientesService } from '../../core/services/clientes.service';
import { ToastService } from '../../core/services/toast.service';
import { ModalComponent } from '../../shared/components/modal/modal';

interface FormaCliente {
  nombre: string;
  telefono: string;
  email: string;
  direccion: string;
  ciudad: string;
  notas: string;
  bloqueado: boolean;
  motivo_bloqueo: string;
  es_prueba: boolean;
}

@Component({
  imports: [FormsModule, ModalComponent],
  selector: 'app-cliente-form-modal',
  standalone: true,
  styleUrl: './cliente-form-modal.scss',
  templateUrl: './cliente-form-modal.html',
})
export class ClienteFormModalComponent implements OnInit {
  readonly cliente = input<Cliente | null>(null);

  readonly guardado = output<Cliente>();
  readonly solicitarEliminar = output<Cliente>();
  readonly cerrado = output<void>();

  private readonly clientesService = inject(ClientesService);
  private readonly toast = inject(ToastService);

  readonly forma: FormaCliente = {
    nombre: '',
    telefono: '',
    email: '',
    direccion: '',
    ciudad: '',
    notas: '',
    bloqueado: false,
    motivo_bloqueo: '',
    es_prueba: false,
  };

  esEdicion = false;
  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly avisoDuplicado = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  private confirmadoDuplicado = false;

  ngOnInit(): void {
    const actual = this.cliente();
    this.esEdicion = actual != null;

    if (actual) {
      this.forma.nombre = actual.nombre;
      this.forma.telefono = actual.telefono ?? '';
      this.forma.email = actual.email ?? '';
      this.forma.direccion = actual.direccion ?? '';
      this.forma.ciudad = actual.ciudad ?? '';
      this.forma.notas = actual.notas ?? '';
      this.forma.bloqueado = actual.bloqueado ?? false;
      this.forma.motivo_bloqueo = actual.motivo_bloqueo ?? '';
      this.forma.es_prueba = actual.es_prueba ?? false;
    }
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  guardar(confirmar = false): void {
    const f = this.forma;

    if (!f.nombre.trim()) {
      this.errorBanner.set('El nombre del cliente es obligatorio.');
      this.errores.set(null);
      return;
    }

    const data: ClientePayload = {
      nombre: f.nombre.trim(),
      telefono: f.telefono.trim() || null,
      email: f.email.trim() || null,
      direccion: f.direccion.trim() || null,
      ciudad: f.ciudad.trim() || null,
      notas: f.notas.trim() || null,
      bloqueado: f.bloqueado,
      motivo_bloqueo: f.bloqueado ? f.motivo_bloqueo.trim() || null : null,
      es_prueba: f.es_prueba,
    };

    if (confirmar || this.confirmadoDuplicado) {
      data.confirmar_duplicado = true;
    }

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    const peticion = this.esEdicion
      ? this.clientesService.actualizar(this.cliente()!.id, data)
      : this.clientesService.crear(data);

    peticion.subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success(this.esEdicion ? 'Cliente actualizado.' : 'Cliente creado.');
          this.guardado.emit(res.data);
        }
      },
      error: (err: ApiError) => {
        this.guardando.set(false);

        if (err.status === 422 && err.errors?.['confirmar_duplicado']) {
          this.avisoDuplicado.set(err.message);
          return;
        }

        this.errorBanner.set(err.message ?? 'No se pudo guardar el cliente.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  confirmarDuplicado(): void {
    this.confirmadoDuplicado = true;
    this.avisoDuplicado.set(null);
    this.guardar(true);
  }

  descartarAviso(): void {
    this.avisoDuplicado.set(null);
  }

  eliminar(): void {
    const actual = this.cliente();
    if (actual) {
      this.solicitarEliminar.emit(actual);
    }
  }

  cerrar(): void {
    this.cerrado.emit();
  }
}
