import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ConfiguracionService } from '../../core/services/configuracion.service';

@Component({
  imports: [RouterLink],
  selector: 'app-footer',
  styleUrl: './footer.scss',
  templateUrl: './footer.html',
})
export class Footer {
  readonly anio = new Date().getFullYear();
  readonly config = inject(ConfiguracionService);

  readonly correo = computed(
    () => this.config.datos().correo?.trim() || 'contacto@everlyboutique.yervixsoft.com',
  );
  readonly direccion = computed(
    () => this.config.datos().direccion?.trim() || 'Calle Panamá 158 entre Argentina',
  );
  readonly horario = computed(
    () =>
      this.config.datos().horario?.trim() ||
      'Lunes a viernes 9:00 – 19:30 · Sábados 9:30 – 14:00',
  );
  readonly telefono = computed(() => '+59178849663');
  readonly whatsappNumero = computed(() => `+${this.config.whatsapp()}`);
  readonly instagram = computed(
    () => this.config.datos().instagram?.trim() || 'https://www.instagram.com/vest.fashionoficial',
  );
  readonly facebook = computed(
    () => this.config.datos().facebook?.trim() || 'https://www.facebook.com/EverlyOficial/',
  );
  readonly tiktok = computed(
    () => this.config.datos().tiktok?.trim() || 'https://www.tiktok.com/@everlyfashion',
  );
}