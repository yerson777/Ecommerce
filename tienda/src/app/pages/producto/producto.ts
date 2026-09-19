import { Component, ElementRef, HostListener, OnInit, signal, ViewChild } from '@angular/core';
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

  readonly zoomActivo = signal(false);
  readonly zoomImagenEstilo = signal<Record<string, string>>({});

  readonly relacionados = signal<ProductoPublico[]>([]);
  readonly flechasRelacionados = signal({ alInicio: true, alFinal: false });
  readonly alturaMediaRelacionados = signal(0);

  @ViewChild('zoomOrigen', { static: false })
  private zoomOrigen?: ElementRef<HTMLElement>;

  @ViewChild('relacionadosPista', { static: false })
  private relacionadosPista?: ElementRef<HTMLElement>;

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
    this.relacionados.set([]);
    this.flechasRelacionados.set({ alInicio: true, alFinal: false });
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
        this.cargarRelacionados();
      },
      error: () => {
        this.error.set('No pudimos cargar la prenda. Probá de nuevo en unos minutos.');
        this.cargando.set(false);
      },
    });
  }

  private cargarRelacionados(): void {
    this.catalogo.relacionados(this.productoId).subscribe({
      next: (res) => {
        this.relacionados.set(res.data ?? []);
        // Espera a que la pista se renderice antes de calcular los límites.
        requestAnimationFrame(() => this.actualizarFlechasRelacionados());
      },
      error: () => undefined,
    });
  }

  imagenRelacionado(producto: ProductoPublico): string | null {
    const principal = producto.imagenes.find((imagen) => imagen.es_principal);
    return principal?.url ?? producto.imagenes[0]?.url ?? null;
  }

  verRelacionado(id: number): void {
    this.router.navigate(['/producto', id]);
  }

  desplazarRelacionados(direccion: number): void {
    const pista = this.relacionadosPista?.nativeElement;
    if (!pista) {
      return;
    }
    const tarjeta = pista.querySelector<HTMLElement>('.rel-card');
    const paso = (tarjeta?.offsetWidth ?? 240) + 16;
    pista.scrollBy({ left: direccion * paso, behavior: 'smooth' });
  }

  actualizarFlechasRelacionados(): void {
    const pista = this.relacionadosPista?.nativeElement;
    if (!pista) {
      return;
    }
    const limite = 1;
    this.flechasRelacionados.set({
      alInicio: pista.scrollLeft <= limite,
      alFinal: pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - limite,
    });

    // Centra las flechas en la mitad de las fotos (borde lateral) y no en la tarjeta completa.
    const foto = pista.querySelector<HTMLElement>('.rel-foto-wrap');
    if (foto) {
      this.alturaMediaRelacionados.set(Math.max(0, Math.round(foto.offsetHeight / 2)));
    }
  }

  @HostListener('window:resize')
  onResize(): void {
    this.actualizarFlechasRelacionados();
  }

  cambiarFoto(url: string): void {
    this.fotoActiva.set(url);
  }

  onZoomMover(evento: MouseEvent): void {
    const origen = this.zoomOrigen?.nativeElement;
    if (!origen) {
      return;
    }

    const rect = origen.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) {
      return;
    }

    const x = this.acotar((evento.clientX - rect.left) / rect.width, 0, 1);
    const y = this.acotar((evento.clientY - rect.top) / rect.height, 0, 1);

    this.zoomImagenEstilo.set({
      'transform-origin': `${x * 100}% ${y * 100}%`,
      transform: 'scale(3)',
    });
  }

  private acotar(valor: number, minimo: number, maximo: number): number {
    return Math.min(Math.max(valor, minimo), maximo);
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