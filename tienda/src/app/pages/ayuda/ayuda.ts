import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

interface PreguntaFrecuente {
  pregunta: string;
  respuesta: string;
}

@Component({
  imports: [CommonModule, RouterLink],
  selector: 'app-ayuda',
  styleUrl: './ayuda.scss',
  templateUrl: './ayuda.html',
})
export class AyudaComponent {
  readonly faqs: PreguntaFrecuente[] = [
    {
      pregunta: '¿De dónde vienen las prendas?',
      respuesta:
        'Trabajamos con moda coreana y americana, seleccionada pieza por pieza. Por eso cada modelo es único o de tirada muy corta y tiene stock limitado.',
    },
    {
      pregunta: '¿Cómo sé si mi talle está disponible?',
      respuesta:
        'En la ficha de cada producto indicamos el talle disponible. Si tu talle no aparece, podés escribirnos y te avisamos cuando ingrese algo similar.',
    },
    {
      pregunta: '¿Puedo reservar una prenda?',
      respuesta:
        'Sí. Al ser unidades únicas, al completar tu compra la prenda queda reservada automáticamente a tu nombre durante 24 horas mientras coordinamos el pago.',
    },
    {
      pregunta: '¿Cuánto demora la entrega?',
      respuesta:
        'En Santa Cruz coordinamos la entrega en 24 a 48 horas. Al resto de Bolivia despachamos por encomienda y suele llegar entre 3 y 7 días hábiles, según la ciudad.',
    },
    {
      pregunta: '¿Qué medios de pago aceptan?',
      respuesta:
        'Aceptamos transferencia bancaria, QR y pago en efectivo coordinado. Al finalizar tu compra te mostramos las opciones disponibles y los datos para abonar.',
    },
    {
      pregunta: '¿Hacen envíos internacionales?',
      respuesta:
        'Por el momento enviamos dentro de Bolivia. Si estás en otro país, escribinos y evaluamos juntas la mejor opción de envío.',
    },
  ];
}