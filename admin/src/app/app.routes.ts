import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { guestGuard } from './core/guards/guest.guard';
import { LayoutComponent } from './pages/layout/layout';
import { LoginComponent } from './pages/login/login';
import { NotFoundComponent } from './pages/not-found/not-found';
import { DashboardComponent } from './pages/dashboard/dashboard';
import { ProductosComponent } from './pages/productos/productos';
import { InventarioComponent } from './pages/inventario/inventario';
import { CategoriasComponent } from './pages/categorias/categorias';
import { TallasMarcasComponent } from './pages/tallas-marcas/tallas-marcas';
import { VentasComponent } from './pages/ventas/ventas';
import { ReservasComponent } from './pages/reservas/reservas';
import { DevolucionesComponent } from './pages/devoluciones/devoluciones';
import { PedidosComponent } from './pages/pedidos/pedidos';
import { ClientesComponent } from './pages/clientes/clientes';
import { PagosComponent } from './pages/pagos/pagos';
import { CajaComponent } from './pages/caja/caja';
import { ReportesComponent } from './pages/reportes/reportes';
import { BannersComponent } from './pages/banners/banners';
import { OfertasComponent } from './pages/ofertas/ofertas';
import { ProveedoresComponent } from './pages/proveedores/proveedores';
import { ComprasComponent } from './pages/compras/compras';
import { ConfiguracionComponent } from './pages/configuracion/configuracion';
import { CuponesComponent } from './pages/cupones/cupones';
import { UsuariosComponent } from './pages/usuarios/usuarios';

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
      { path: 'categorias', component: CategoriasComponent },
      { path: 'tallas-marcas', component: TallasMarcasComponent },
      { path: 'ventas', component: VentasComponent },
      { path: 'reservas', component: ReservasComponent },
      { path: 'devoluciones', component: DevolucionesComponent },
      { path: 'pedidos', component: PedidosComponent },
      { path: 'clientes', component: ClientesComponent },
      { path: 'pagos', component: PagosComponent },
      { path: 'caja', component: CajaComponent },
      { path: 'reportes', component: ReportesComponent },
      { path: 'banners', component: BannersComponent },
      { path: 'ofertas', component: OfertasComponent },
      { path: 'proveedores', component: ProveedoresComponent },
      { path: 'compras', component: ComprasComponent },
      { path: 'cupones', component: CuponesComponent },
      { path: 'configuracion', component: ConfiguracionComponent },
      { path: 'usuarios', component: UsuariosComponent },
    ],
  },
  { path: '**', component: NotFoundComponent },
];