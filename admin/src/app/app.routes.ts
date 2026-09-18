import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { guestGuard } from './core/guards/guest.guard';
import { LayoutComponent } from './pages/layout/layout';
import { LoginComponent } from './pages/login/login';
import { NotFoundComponent } from './pages/not-found/not-found';
import { DashboardComponent } from './pages/dashboard/dashboard';
import { ProductosComponent } from './pages/productos/productos';
import { InventarioComponent } from './pages/inventario/inventario';
import { VentasComponent } from './pages/ventas/ventas';
import { PedidosComponent } from './pages/pedidos/pedidos';
import { ClientesComponent } from './pages/clientes/clientes';
import { PagosComponent } from './pages/pagos/pagos';
import { ReportesComponent } from './pages/reportes/reportes';
import { BannersComponent } from './pages/banners/banners';

export const routes: Routes = [
  { path: 'login', component: LoginComponent, canActivate: [guestGuard] },
  {
    path: '',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: '/dashboard', pathMatch: 'full' },
      { path: 'dashboard', component: DashboardComponent },
      { path: 'productos', component: ProductosComponent },
      { path: 'inventario', component: InventarioComponent },
      { path: 'ventas', component: VentasComponent },
      { path: 'pedidos', component: PedidosComponent },
      { path: 'clientes', component: ClientesComponent },
      { path: 'pagos', component: PagosComponent },
      { path: 'reportes', component: ReportesComponent },
      { path: 'banners', component: BannersComponent },
    ],
  },
  { path: '**', component: NotFoundComponent },
];