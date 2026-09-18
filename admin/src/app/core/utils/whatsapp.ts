export function numeroWhatsApp(telefono: string | null): string {
  return (telefono ?? '').replace(/[^\d]/g, '');
}

export function enlaceWhatsApp(telefono: string | null, mensaje?: string): string {
  const numero = numeroWhatsApp(telefono);

  if (!numero) {
    return '';
  }

  const texto = mensaje ?? 'Hola, te escribimos de Everly Boutique.';

  return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
}