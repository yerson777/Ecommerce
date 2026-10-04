import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { CatalogoService } from '../../core/services/catalogo.service';
import { CartService } from '../../core/services/cart.service';
import { BannerCarouselService } from '../../core/services/banner-carousel.service';
import { ProductoPublico, CategoriaRef, TallaRef } from '../../core/models/producto';
import { formatearPrecio } from '../../core/utils/precio';
import { imagenPrincipal } from '../../core/utils/producto';

@Component({
  imports: [CommonModule],
  providers: [BannerCarouselService],
  selector: 'app-catalogo',
  styleUrl: './catalogo.scss',
  templateUrl: './catalogo.html',
})
export class CatalogoComponent implements OnInit, OnDestroy {
  readonly cargando = signal(true);
  readonly error = signal<string | null>(null);
  readonly productos = signal<ProductoPublico[]>([]);
  readonly categorias = signal<CategoriaRef[]>([]);
  readonly tallas = signal<TallaRef[]>([]);

  readonly carrusel = inject(BannerCarouselService);

  readonly formatearPrecio = formatearPrecio;
  readonly imagenPrincipal = imagenPrincipal;

  readonly busqueda = signal('');
  readonly categoriaActiva = signal<number | null>(null);
  readonly tallaActiva = signal<number | null>(null);

  readonly pagina = signal(1);
  readonly total = signal(0);
  readonly ultimaPagina = signal(1);
  readonly porPagina = signal(12);

  constructor(
    private readonly catalogo: CatalogoService,
    private readonly carrito: CartService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    this.cargarFiltros();
    this.carrusel.cargar();
    this.cargar();
  }

  ngOnDestroy(): void {
    this.carrusel.destruir();
  }

  enCarrito(id: number): boolean {
    return this.carrito.contiene(id);
  }

  cargarFiltros(): void {
    this.catalogo.categorias().subscribe({
      next: (res) => this.categorias.set(res.data ?? []),
    });
    this.catalogo.tallas().subscribe({
      next: (res) => this.tallas.set(res.data ?? []),
    });
  }

  cargar(): void {
    this.cargando.set(true);
    this.error.set(null);
    this.catalogo
      .listar({
        categoria: this.categoriaActiva(),
        talla: this.tallaActiva(),
        busqueda: this.busqueda(),
        page: this.pagina(),
        per_page: this.porPagina(),
      })
      .subscribe({
        next: (res) => {
          const paginado = res.data;
          if (!paginado) {
            this.productos.set([]);
            this.total.set(0);
            this.ultimaPagina.set(1);
            this.cargando.set(false);
            return;
          }
          this.productos.set(paginado.data);
          this.total.set(paginado.total);
          this.ultimaPagina.set(paginado.last_page);
          this.cargando.set(false);
        },
        error: () => {
          this.error.set('No pudimos cargar el catálogo. Verificá tu conexión e intentá de nuevo.');
          this.cargando.set(false);
        },
      });
  }

  cambiarCategoria(id: number | null): void {
    this.categoriaActiva.set(id);
    this.pagina.set(1);
    this.cargar();
  }

  cambiarTalla(id: number | null): void {
    this.tallaActiva.set(id);
    this.pagina.set(1);
    this.cargar();
  }

  buscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.busqueda.set('');
    this.categoriaActiva.set(null);
    this.tallaActiva.set(null);
    this.pagina.set(1);
    this.cargar();
  }

  cambiarPagina(siguiente: number): void {
    if (siguiente < 1 || siguiente > this.ultimaPagina()) {
      return;
    }
    this.pagina.set(siguiente);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    this.cargar();
  }

  verDetalle(id: number): void {
    this.router.navigate(['/producto', id]);
  }

  agregarAlCarrito(producto: ProductoPublico): void {
    const imagen = this.imagenPrincipal(producto);
    this.carrito.agregar({
      producto_id: producto.id,
      codigo: producto.codigo,
      nombre: producto.nombre,
      color: producto.color,
      talla: producto.talla?.nombre ?? null,
      precio: producto.precio,
      imagen,
    });
  }
}
