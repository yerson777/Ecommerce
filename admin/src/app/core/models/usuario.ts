export type Rol = 'super_admin' | 'admin' | 'vendedor';

export interface Usuario {
  id: number;
  name: string;
  email: string;
  role: Rol;
  etiqueta_rol: string;
  activo: boolean;
  created_at: string;
}

export type UsuarioDatos = {
  name: string;
  email: string;
  role: Rol;
  password?: string;
  activo: boolean;
};

export const ROL_ETIQUETAS: Record<Rol, string> = {
  super_admin: 'Super Administrador',
  admin: 'Administrador',
  vendedor: 'Vendedor',
};

const MODULOS_SOLO_ADMIN = [
  'dashboard',
  'pagos',
  'caja',
  'reportes',
  'configuracion',
  'cupones',
  'usuarios',
];

export function puedeVerModulo(role: string | undefined, modulo: string): boolean {
  if (role === 'super_admin') {
    return true;
  }
  if (role === 'admin') {
    return modulo !== 'usuarios';
  }
  if (role === 'vendedor') {
    return !MODULOS_SOLO_ADMIN.includes(modulo);
  }
  return false;
}