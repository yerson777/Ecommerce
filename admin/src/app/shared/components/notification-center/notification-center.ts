import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { interval } from 'rxjs';
import { Router } from '@angular/router';
import { Notificacion, NotificacionesService } from '../../../core/services/notificaciones.service';
import { ToastService } from '../../../core/services/toast.service';

const REFRESCO_MS = 15000;

const ICONO_POR_TIPO: Record<string, string> = {
  pedido_creado: '📦',
  pedido_confirmado: '✅',
  pedido_cancelado: '⛔',
  pedido_completado: '🏁',
  pago_registrado: '💳',
  pago_parcial: '💳',
  pago_confirmado: '💰',
  comprobante_recibido: '🧾',
  comprobante_rechazado: '🧾',
  saldo_pendiente: '◷',
};

@Component({
  imports: [],
  selector: 'app-notification-center',
  standalone: true,
  styleUrl: './notification-center.scss',
  templateUrl: './notification-center.html',
})
export class NotificationCenterComponent implements OnInit {
  private readonly notificacionesService = inject(NotificacionesService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly abierto = signal(false);
  readonly cargando = signal(false);
  readonly noLeidas = signal(0);
  readonly notificaciones = signal<Notificacion[]>([]);

  private ultimoConteo: number | null = null;

  ngOnInit(): void {
    this.cargar();
    interval(REFRESCO_MS)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargar());
  }

  cargar(): void {
    if (this.cargando()) {
      return;
    }

    this.cargando.set(true);

    this.notificacionesService.listar({ solo_no_leidas: true, por_pagina: 12 }).subscribe({
      next: (res) => {
        this.cargando.set(false);

        if (!res.success) {
          return;
        }

        this.notificaciones.set(res.data ?? []);
        const total = res.meta?.no_leidas ?? (res.data?.length ?? 0);
        this.noLeidas.set(total);

        if (this.ultimoConteo !== null && total > this.ultimoConteo) {
          this.toast.info('🔔 ¡Nueva actividad! Revisá los pedidos recién recibidos.');
        }

        this.ultimoConteo = total;
      },
      error: () => {
        this.cargando.set(false);
      },
    });
  }

  alternar(): void {
    this.abierto.update((abierto) => !abierto);
    if (this.abierto()) {
      this.cargar();
    }
  }

  cerrar(): void {
    this.abierto.set(false);
  }

  irAPedidos(): void {
    this.abierto.set(false);
    this.router.navigate(['/pedidos']);
  }

  marcarTodasLeidas(): void {
    this.abierto.set(false);
    this.notificacionesService.marcarTodasLeidas().subscribe({
      next: () => {
        this.noLeidas.set(0);
        this.ultimoConteo = 0;
        this.notificaciones.set([]);
        this.toast.success('Notificaciones marcadas como leídas.');
      },
      error: () => undefined,
    });
  }

  iconoTipo(tipo: string): string {
    return ICONO_POR_TIPO[tipo] ?? '🔔';
  }
}