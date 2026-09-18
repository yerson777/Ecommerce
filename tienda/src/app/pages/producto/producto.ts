import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CatalogoService } from '../../core/services/catalogo.service';
import { CartService } from '../../core/services/cart.service';
import { ProductoPublico } from '../../core/models/producto';
import { formatearPrecio } from '../../core/utils/precio';

@Component({
  imports: [CommonModule, RouterLink],
  selector: 'app-producto',
  styleUrl: './producto.scss',
  templateUrl: './producto.html',
})
export class ProductoComponent implements OnInit {
  readonly cargando = signal(true);
  readonly error = signal<string | null>(null);
  readonly producto = signal<ProductoPublico | null>(null);
  readonly fotoActiva = signal<string | null>(null);
  private productoId = 0;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly catalogo: CatalogoService,
    private readonly carrito: CartService,
  ) {}

  ngOnInit(): void {
    this.route.paramMap.subscribe((params) => {
      const id = Number(params.get('id'));
      if (!Number.isInteger(id) || id <= 0) {
        this.router.navigate(['/']);
        return;
      }
      this.productoId = id;
      this.cargar();
    });
  }

  formatearPrecio(valor: number | string): string {
    return formatearPrecio(valor);
  }

  enCarrito(): boolean {
    return this.carrito.contiene(this.productoId);
  }

  cargar(): void {
    this.cargando.set(true);
    this.error.set(null);
    this.catalogo.detalle(this.productoId).subscribe({
      next: (res) => {
        const producto = res.data;
        if (!producto) {
          this.error.set('Esta prenda ya no está disponible.');
          this.cargando.set(false);
          return;
        }
        this.producto.set(producto);
        this.fotoActiva.set(
          producto.imagenes.find((imagen) => imagen.es_principal)?.url ??
            producto.imagenes[0]?.url ??
            null,
        );
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No pudimos cargar la prenda. Probá de nuevo en unos minutos.');
        this.cargando.set(false);
      },
    });
  }

  cambiarFoto(url: string): void {
    this.fotoActiva.set(url);
  }

  agregarAlCarrito(): void {
    const producto = this.producto();
    if (!producto || this.enCarrito()) {
      return;
    }
    this.carrito.agregar({
      producto_id: producto.id,
      codigo: producto.codigo,
      nombre: producto.nombre,
      color: producto.color,
      talla: producto.talla?.nombre ?? null,
      precio: producto.precio,
      imagen: this.fotoActiva(),
    });
  }
}