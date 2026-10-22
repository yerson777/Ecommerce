const ESTILOS_REPORTE_ID = 'everly-estilos-impresion-reporte';

export function imprimirReporteA4(titulo = 'Everly · Reportes y estadísticas'): void {
  const hoy = new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });

  const style = document.createElement('style');
  style.id = ESTILOS_REPORTE_ID;
  style.textContent = `
    @page {
      size: A4 landscape;
      margin: 9mm 9mm 12mm 9mm;
      @top-left {
        content: "${titulo}";
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 9pt;
        font-weight: 600;
        color: #7a5a6b;
      }
      @bottom-left {
        content: "Generado el " "${hoy}";
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 8pt;
        color: #999;
      }
      @bottom-right {
        content: "Página " counter(page) " de " counter(pages);
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 8pt;
        color: #999;
      }
    }
  `;

  let liberado = false;
  let listener = (): void => {
    if (liberado) {
      return;
    }
    liberado = true;
    document.body.classList.remove('imprimiendo-reporte');
    style.remove();
    window.removeEventListener('afterprint', listener);
  };

  window.addEventListener('afterprint', listener);

  document.body.classList.add('imprimiendo-reporte');
  document.head.appendChild(style);

  window.print();

  setTimeout(listener, 1500);
}