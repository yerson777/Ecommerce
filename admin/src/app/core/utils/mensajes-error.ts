export interface ContextoError {
  status?: number;
  code?: string;
  name?: string;
}

const CREDENCIALES =
  'El correo o la contraseña son incorrectos. Revisá tus datos e intentá de nuevo.';
const SIN_CONEXION =
  'No hay conexión a internet. Verificá tu conexión e intentá de nuevo.';
const SERVIDOR =
  'El servidor no responde en este momento. Intentá de nuevo en unos minutos.';
const TIEMPO =
  'La solicitud tardó demasiado. Intentá de nuevo.';
const SESION =
  'Tu sesión expiró. Iniciá sesión nuevamente.';
const PERMISO =
  'No tenés permiso para realizar esta acción.';
const NO_ENCONTRADO =
  'No se encontró lo que buscabas.';
const DEMASIADOS_INTENTOS =
  'Demasiados intentos. Esperá un momento e intentá de nuevo.';
const VALIDACION =
  'Revisá los datos ingresados: hay campos incompletos o con formato incorrecto.';
const INESPERADO =
  'Ocurrió un error inesperado. Intentá de nuevo.';

export function mensajeError(error: ContextoError, esLogin = false): string {
  if (error.name === 'TimeoutError' || error.code === 'timeout') {
    return TIEMPO;
  }

  if (error.status === 0) {
    return SIN_CONEXION;
  }

  if (error.status === 401) {
    return esLogin ? CREDENCIALES : SESION;
  }

  if (error.status === 403) {
    return PERMISO;
  }

  if (error.status === 404) {
    return NO_ENCONTRADO;
  }

  if (error.status === 429) {
    return DEMASIADOS_INTENTOS;
  }

  if (error.code === 'credenciales_incorrectas' || (esLogin && error.status === 422)) {
    return CREDENCIALES;
  }

  if (error.status === 422) {
    return VALIDACION;
  }

  if (error.status !== undefined && error.status >= 500) {
    return SERVIDOR;
  }

  return INESPERADO;
}
