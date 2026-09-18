import { Routes } from '@angular/router';
import { HomeComponent } from './pages/home/home';
import { ProductoComponent } from './pages/producto/producto';
import { CarritoComponent } from './pages/carrito/carrito';
import { CheckoutComponent } from './pages/checkout/checkout';
import { ConfirmacionComponent } from './pages/confirmacion/confirmacion';
import { SeguimientoComponent } from './pages/seguimiento/seguimiento';
import { NosotrosComponent } from './pages/nosotros/nosotros';
import { AyudaComponent } from './pages/ayuda/ayuda';
import { ContactoComponent } from './pages/contacto/contacto';
import { CuentaComponent } from './pages/cuenta/cuenta';
import { NotFoundComponent } from './pages/not-found/not-found';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'producto/:id', component: ProductoComponent },
  { path: 'carrito', component: CarritoComponent },
  { path: 'checkout', component: CheckoutComponent },
  { path: 'confirmacion', component: ConfirmacionComponent },
  { path: 'seguimiento', component: SeguimientoComponent },
  { path: 'nosotros', component: NosotrosComponent },
  { path: 'ayuda', component: AyudaComponent },
  { path: 'contacto', component: ContactoComponent },
  { path: 'cuenta', component: CuentaComponent },
  { path: '**', component: NotFoundComponent },
];