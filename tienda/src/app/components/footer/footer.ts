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
    () => this.config.datos().direccion?.trim() || 'Av. Las Flores 123, Cochabamba',
  );
  readonly horario = computed(
    () => this.config.datos().horario?.trim() || 'Lun a Sáb · 9:00 a 19:30',
  );
  readonly telefono = computed(() => `+${this.config.whatsapp()}`);
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