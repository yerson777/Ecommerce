import { HttpClient } from '@angular/common/http';
import { Injectable, computed, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiResponse } from '../models/api-response';
import { ConfiguracionTienda } from '../models/configuracion';
import { configurarFormatoPrecio } from '../utils/precio';
import { ApiService } from './api.service';

const NOMBRE_RESPALDO = 'Everly Boutique';
const WHATSAPP_RESPALDO = '59162640247';

function configuracionVacia(): ConfiguracionTienda {
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

@Injectable({ providedIn: 'root' })
export class ConfiguracionService extends ApiService {
  readonly datos = signal<ConfiguracionTienda>(configuracionVacia());

  readonly nombre = computed(() => this.datos().nombre?.trim() || NOMBRE_RESPALDO);
  readonly whatsapp = computed(() => this.datos().whatsapp?.trim() || WHATSAPP_RESPALDO);
  readonly logoUrl = computed(() => this.datos().logo_url);
  readonly marca = computed(() => {
    const [principal, ...resto] = this.nombre().split(/\s+/);
    return { principal, secundaria: resto.join(' ') };
  });
  readonly whatsappUrl = computed(() => `https://wa.me/${this.whatsapp()}`);
  readonly whatsappConsultaUrl = computed(
    () => `${this.whatsappUrl()}?text=${encodeURIComponent('¡Hola! Tengo una consulta.')}`,
  );

  constructor(http: HttpClient) {
    super(http);
  }

  /**
   * Carga la configuración pública antes del arranque. Si la API falla, la
   * tienda sigue funcionando con sus valores de respaldo.
   */
  async inicializar(): Promise<void> {
    try {
      const respuesta = await firstValueFrom(
        this.http.get<ApiResponse<ConfiguracionTienda>>(`${this.baseUrl}/v1/store/configuracion`),
      );

      if (respuesta.success && respuesta.data) {
        this.datos.set(this.normalizar(respuesta.data));
      }
    } catch {
      // Se conservan los valores de respaldo.
    }

    this.aplicarFormatoPrecio();
  }

  private aplicarFormatoPrecio(): void {
    const moneda = this.datos().moneda;

    configurarFormatoPrecio({
      simbolo: moneda.simbolo,
      decimales: moneda.decimales,
      separadorMiles: moneda.separador_miles,
      separadorDecimal: moneda.separador_decimal,
    });
  }

  private normalizar(datos: ConfiguracionTienda): ConfiguracionTienda {
    return {
      ...datos,
      textos: {
        cambios_devoluciones: datos.textos?.cambios_devoluciones ?? null,
        envios: datos.textos?.envios ?? null,
        terminos: datos.textos?.terminos ?? null,
      },
      moneda: {
        simbolo: datos.moneda?.simbolo || 'Bs',
        decimales: Number.isFinite(datos.moneda?.decimales) ? datos.moneda.decimales : 2,
        separador_miles: datos.moneda?.separador_miles ?? '',
        separador_decimal: datos.moneda?.separador_decimal || '.',
      },
    };
  }
}
