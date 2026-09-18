import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

@Component({
  imports: [CommonModule, FormsModule, RouterLink],
  selector: 'app-footer',
  styleUrl: './footer.scss',
  templateUrl: './footer.html',
})
export class Footer {
  readonly anio = new Date().getFullYear();
  readonly suscripto = signal(false);

  email = '';

  suscribir(): void {
    if (!this.email.trim()) {
      return;
    }
    this.suscripto.set(true);
    this.email = '';
  }
}