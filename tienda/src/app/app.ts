import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { Header } from './components/header/header';
import { Footer } from './components/footer/footer';
import { CartToastComponent } from './components/cart-toast/cart-toast';
import { ConfiguracionService } from './core/services/configuracion.service';

@Component({
  imports: [RouterOutlet, Header, Footer, CartToastComponent],
  selector: 'app-root',
  styleUrl: './app.scss',
  templateUrl: './app.html',
})
export class App {
  readonly config = inject(ConfiguracionService);
}