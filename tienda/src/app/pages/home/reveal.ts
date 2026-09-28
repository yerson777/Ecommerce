import { Directive, ElementRef, OnDestroy, OnInit, inject, input } from '@angular/core';
import { animate, inView, type AnimationPlaybackControls } from 'motion';

export type RevealVariant = 'up' | 'left' | 'right' | 'scale' | 'fade';

const DESDE: Record<RevealVariant, string> = {
  up: 'translateY(32px)',
  left: 'translateX(-40px)',
  right: 'translateX(40px)',
  scale: 'scale(0.94)',
  fade: 'none',
};

const HASTA: Record<RevealVariant, string> = {
  up: 'translateY(0px)',
  left: 'translateX(0px)',
  right: 'translateX(0px)',
  scale: 'scale(1)',
  fade: 'none',
};

const CURVA = [0.22, 1, 0.36, 1] as const;

export function motionReducido(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false;
  }
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

@Directive({
  selector: '[reveal]',
})
export class RevealDirective implements OnInit, OnDestroy {
  readonly reveal = input<RevealVariant>('up');
  readonly revealDelay = input(0);
  readonly revealDuration = input(0.55);

  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef);
  private stopObservando?: VoidFunction;
  private animacion?: AnimationPlaybackControls;

  ngOnInit(): void {
    const host = this.el.nativeElement;

    if (motionReducido() || typeof IntersectionObserver === 'undefined') {
      this.mostrar();
      return;
    }

    this.ocultar();
    this.stopObservando = inView(
      host,
      () => {
        this.stopObservando?.();
        this.animacion = animate(
          host,
          {
            opacity: [0, 1],
            transform: [DESDE[this.reveal()], HASTA[this.reveal()]],
          },
          {
            duration: this.revealDuration(),
            delay: this.revealDelay(),
            ease: [...CURVA],
          },
        );
      },
      { amount: 0.15, margin: '-10% 0px -10% 0px' },
    );
  }

  ngOnDestroy(): void {
    this.stopObservando?.();
    this.animacion?.stop();
  }

  private ocultar(): void {
    this.el.nativeElement.style.opacity = '0';
    this.el.nativeElement.style.transform = DESDE[this.reveal()];
  }

  private mostrar(): void {
    this.el.nativeElement.style.opacity = '1';
    this.el.nativeElement.style.transform = HASTA[this.reveal()];
  }
}
