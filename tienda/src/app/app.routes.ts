import { Routes } from '@angular/router';
import { HomeComponent } from './pages/home/home';
import { CatalogoComponent } from './pages/catalogo/catalogo';
import { ProductoComponent } from './pages/producto/producto';
import { CarritoComponent } from './pages/carrito/carrito';
import { CheckoutComponent } from './pages/checkout/checkout';
import { ConfirmacionComponent } from './pages/confirmacion/confirmacion';
import { SeguimientoComponent } from './pages/seguimiento/seguimiento';
import { NosotrosComponent } from './pages/nosotros/nosotros';
import { AyudaComponent } from './pages/ayuda/ayuda';
import { ContactoComponent } from './pages/contacto/contacto';
import { CuentaComponent } from './pages/cuenta/cuenta';
import { IngresarComponent } from './pages/ingresar/ingresar';
import { RegistrarmeComponent } from './pages/registrarme/registrarme';
import { RecuperarComponent } from './pages/recuperar/recuperar';
import { RestablecerComponent } from './pages/restablecer/restablecer';
import { MiPerfilComponent } from './pages/mi-perfil/mi-perfil';
import { MisPedidosComponent } from './pages/mis-pedidos/mis-pedidos';
import { MiPedidoDetalleComponent } from './pages/mis-pedido-detalle/mis-pedido-detalle';
import { clienteAuthGuard } from './core/guards/cliente-auth.guard';
import { NotFoundComponent } from './pages/not-found/not-found';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'catalogo', component: CatalogoComponent },
  { path: 'producto/:id', component: ProductoComponent },
  { path: 'carrito', component: CarritoComponent },
  { path: 'checkout', component: CheckoutComponent },
  { path: 'confirmacion', component: ConfirmacionComponent },
  { path: 'seguimiento', component: SeguimientoComponent },
  { path: 'nosotros', component: NosotrosComponent },
  { path: 'ayuda', component: AyudaComponent },
  { path: 'contacto', component: ContactoComponent },
  { path: 'cuenta', component: CuentaComponent },
  { path: 'ingresar', component: IngresarComponent },
  { path: 'registrarme', component: RegistrarmeComponent },
  { path: 'recuperar', component: RecuperarComponent },
  { path: 'restablecer', component: RestablecerComponent },
  { path: 'mi-perfil', component: MiPerfilComponent, canActivate: [clienteAuthGuard] },
  { path: 'mis-pedidos', component: MisPedidosComponent, canActivate: [clienteAuthGuard] },
  { path: 'mis-pedidos/:numero', component: MiPedidoDetalleComponent, canActivate: [clienteAuthGuard] },
  { path: '**', component: NotFoundComponent },
];