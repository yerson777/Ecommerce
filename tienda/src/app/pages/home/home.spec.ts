import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { HomeComponent } from './home';
import { CatalogoService } from '../../core/services/catalogo.service';
import { CartService } from '../../core/services/cart.service';

class CatalogoMock {
  banners() {
    return of({ data: [{ id: 1, url: '/a.jpg', titulo: 'T', subtitulo: 'S', enlace: null, orden: 0 }] });
  }
  listar() {
    return of({
      data: {
        data: [
          {
            id: 7,
            codigo: 'EV-07',
            nombre: 'Vestido floral',
            color: 'Rojo',
            descripcion: null,
            precio: '180',
            estado: 'disponible',
            es_nuevo: true,
            categoria: null,
            talla: { id: 2, nombre: 'M' },
            imagenes: [{ id: 1, url: '/p.jpg', es_principal: true, orden: 0 }],
          },
        ],
        total: 1,
        last_page: 1,
      },
    });
  }
}

class CartMock {
  contiene() {
    return false;
  }
  agregar() {
    return true;
  }
}

describe('HomeComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HomeComponent],
      providers: [
        provideRouter([]),
        { provide: CatalogoService, useClass: CatalogoMock },
        { provide: CartService, useClass: CartMock },
      ],
    }).compileComponents();
  });

  it('renderiza todas las secciones del patrón Feature-Rich Showcase', () => {
    const fixture = TestBed.createComponent(HomeComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('h1')).toBeTruthy();
    expect(fixture.nativeElement.querySelectorAll('.feature').length).toBe(6);
    expect(fixture.nativeElement.querySelectorAll('.paso').length).toBe(3);
    expect(fixture.nativeElement.querySelectorAll('.testimonio').length).toBe(3);
    expect(fixture.nativeElement.querySelectorAll('.prenda').length).toBe(1);
    expect(fixture.nativeElement.querySelector('.carrusel')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('[data-cta-flotante]')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('#como-comprar')).toBeTruthy();
  });

  it('no deja elementos invisibles si el reveal no puede observar', () => {
    const fixture = TestBed.createComponent(HomeComponent);
    fixture.detectChanges();
    const features = fixture.nativeElement.querySelectorAll('.feature') as NodeListOf<HTMLElement>;
    features.forEach((el) => {
      expect(el.style.opacity).toBe('1');
    });
  });
});
