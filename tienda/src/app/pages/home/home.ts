import {
  AfterViewInit,
  Component,
  ElementRef,
  OnDestroy,
  OnInit,
  inject,
  signal,
} from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { animate, scroll, stagger } from 'motion';
import { CatalogoService } from '../../core/services/catalogo.service';
import { CartService } from '../../core/services/cart.service';
import { BannerCarouselService } from '../../core/services/banner-carousel.service';
import { ProductoPublico } from '../../core/models/producto';
import { formatearPrecio } from '../../core/utils/precio';
import { esDisponible, estadoTexto, imagenPrincipal } from '../../core/utils/producto';
import { RevealDirective, motionReducido } from './reveal';

type Icono = 'unidad' | 'reserva' | 'pago' | 'entrega' | 'seguimiento' | 'whatsapp';

interface ItemEstatico {
  readonly icono: Icono;
  readonly titulo: string;
  readonly texto: string;
}

interface Paso {
  readonly numero: string;
  readonly titulo: string;
  readonly texto: string;
}

interface Testimonio {
  readonly nombre: string;
  readonly ciudad: string;
  readonly texto: string;
}

const PROMESAS: readonly ItemEstatico[] = [
  {
    icono: 'unidad',
    titulo: 'Sin stock que esperar',
    texto: 'Cada pieza es una sola unidad.',
  },
  {
    icono: 'reserva',
    titulo: 'Confirmación al momento',
    texto: 'Apartamos tu prenda al confirmar el pago.',
  },
  {
    icono: 'seguimiento',
    titulo: 'Seguimiento en línea',
    texto: 'Mirá tu pedido cuando quieras.',
  },
];

const FEATURES: readonly ItemEstatico[] = [
  {
    icono: 'unidad',
    titulo: 'Una sola unidad',
    texto:
      'Cada prenda existe en una única pieza. Cuando la comprás sale del catálogo para siempre.',
  },
  {
    icono: 'reserva',
    titulo: 'Reserva inmediata',
    texto:
      'Confirmás el pago y la prenda queda apartada al instante, sin listas de espera ni fechas de reposición.',
  },
  {
    icono: 'pago',
    titulo: 'Pago seguro',
    texto:
      'Transferencia, QR o efectivo con comprobante. Vos elegís cómo pagar y nos mandás el respaldo.',
  },
  {
    icono: 'entrega',
    titulo: 'Entrega coordinada',
    texto: 'Acordamos día, horario y lugar que te quede cómodo. Sin sorpresas ni pedidos perdidos.',
  },
  {
    icono: 'seguimiento',
    titulo: 'Seguimiento en vivo',
    texto:
      'Con tu número de pedido ves en qué estado está, paso a paso, desde que confirmamos hasta que lo recibís.',
  },
  {
    icono: 'whatsapp',
    titulo: 'Atención por WhatsApp',
    texto:
      'Escribinos directo y te respondemos personas, no robots. Te mandamos fotos reales antes de cobrar.',
  },
];

const PASOS: readonly Paso[] = [
  {
    numero: '01',
    titulo: 'Elegí tu prenda',
    texto: 'Recorré el catálogo, mirá fotos y medidas, y agregá al carrito lo que te guste.',
  },
  {
    numero: '02',
    titulo: 'Reservá y pagá',
    texto: 'Completá tus datos, mandá el comprobante y confirmamos la reserva en el momento.',
  },
  {
    numero: '03',
    titulo: 'Coordinamos la entrega',
    texto: 'Acordamos dónde y cuándo te la entregamos. Después seguís tu pedido online.',
  },
];

const TESTIMONIOS: readonly Testimonio[] = [
  {
    nombre: 'Lucía M.',
    ciudad: 'Santa Cruz',
    texto:
      'Encontré un vestido que buscaba hace semanas y era el único. Me respondieron por WhatsApp el mismo día y llegó impecable.',
  },
  {
    nombre: 'Camila R.',
    ciudad: 'La Paz',
    texto:
      'Me da mucho miedo comprar ropa online, pero me mandaron fotos reales antes de pagar. Es la prenda que más uso.',
  },
  {
    nombre: 'Andrea P.',
    ciudad: 'Cochabamba',
    texto:
      'El seguimiento del pedido me sirvió un montón. Sabía exactamente cuándo pasar a buscarlo y no tuve que volver.',
  },
];

@Component({
  imports: [RouterLink, RevealDirective],
  providers: [BannerCarouselService],
  selector: 'app-home',
  styleUrl: './home.scss',
  templateUrl: './home.html',
})
export class HomeComponent implements OnInit, AfterViewInit, OnDestroy {
  readonly promesas = PROMESAS;
  readonly features = FEATURES;
  readonly pasos = PASOS;
  readonly testimonios = TESTIMONIOS;

  readonly carrusel = inject(BannerCarouselService);

  readonly destacados = signal<ProductoPublico[]>([]);
  readonly estadoDestacados = signal<'cargando' | 'listo' | 'error'>('cargando');

  private stopScroll?: VoidFunction;
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  constructor(
    private readonly catalogo: CatalogoService,
    private readonly carrito: CartService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    this.carrusel.cargar({ autoplayPermitido: !motionReducido() });
    this.cargarDestacados();
  }

  ngAfterViewInit(): void {
    this.animarHero();
    this.activarCtaFlotante();
  }

  ngOnDestroy(): void {
    this.carrusel.destruir();
    this.stopScroll?.();
  }

  /* ---------- Datos ---------- */

  private cargarDestacados(): void {
    this.catalogo.listar({ page: 1, per_page: 4 }).subscribe({
      next: (res) => {
        this.destacados.set(res.data?.data ?? []);
        this.estadoDestacados.set('listo');
      },
      error: () => this.estadoDestacados.set('error'),
    });
  }

  reintentarDestacados(): void {
    this.estadoDestacados.set('cargando');
    this.cargarDestacados();
  }

  /* ---------- Animaciones (motion) ---------- */

  private animarHero(): void {
    if (motionReducido()) {
      return;
    }
    const items = this.host.nativeElement.querySelectorAll<HTMLElement>('[data-hero]');
    if (items.length === 0) {
      return;
    }

    for (const item of items) {
      item.style.opacity = '0';
      item.style.transform = 'translateY(28px)';
    }

    animate(
      items,
      { opacity: [0, 1], transform: ['translateY(28px)', 'translateY(0px)'] },
      {
        duration: 0.6,
        delay: stagger(0.08, { startDelay: 0.08 }),
        ease: [0.22, 1, 0.36, 1],
      },
    );
  }

  private activarCtaFlotante(): void {
    const hero = this.host.nativeElement.querySelector<HTMLElement>('[data-hero-zona]');
    const barra = this.host.nativeElement.querySelector<HTMLElement>('[data-cta-flotante]');
    if (!hero || !barra) {
      return;
    }

    let visible = false;
    this.stopScroll = scroll(
      (progress) => {
        const debeVerse = progress > 0.5;
        if (debeVerse === visible) {
          return;
        }
        visible = debeVerse;
        barra.classList.toggle('cta-flotante--visible', debeVerse);
      },
      { target: hero, axis: 'y', offset: ['start start', 'end start'] },
    );
  }

  /* ---------- Navegación ---------- */

  irAComoComprar(): void {
    document
      .getElementById('como-comprar')
      ?.scrollIntoView({ behavior: motionReducido() ? 'auto' : 'smooth', block: 'start' });
  }

  verDetalle(id: number): void {
    this.router.navigate(['/producto', id]);
  }

  /* ---------- Productos ---------- */

  readonly formatearPrecio = formatearPrecio;
  readonly imagenPrincipal = imagenPrincipal;
  readonly esDisponible = esDisponible;
  readonly estadoTexto = estadoTexto;

  enCarrito(id: number): boolean {
    return this.carrito.contiene(id);
  }

  agregarAlCarrito(producto: ProductoPublico): void {
    if (!this.esDisponible(producto)) {
      return;
    }
    this.carrito.agregar({
      producto_id: producto.id,
      codigo: producto.codigo,
      nombre: producto.nombre,
      color: producto.color,
      talla: producto.talla?.nombre ?? null,
      precio: producto.precio,
      imagen: this.imagenPrincipal(producto),
    });
  }
}
