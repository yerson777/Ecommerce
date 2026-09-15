import { Component } from '@angular/core';
import { CatalogoService } from '../../core/services/catalogo.service';

@Component({
  imports: [],
  selector: 'app-home',
  styleUrl: './home.scss',
  templateUrl: './home.html',
})
export class HomeComponent {
  backendStatus = false;

  constructor(private readonly catalogo: CatalogoService) {
    this.catalogo.ping().subscribe({
      next: () => (this.backendStatus = true),
      error: () => (this.backendStatus = false),
    });
  }
}