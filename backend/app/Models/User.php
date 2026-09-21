<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROL_SUPER_ADMIN = 'super_admin';
    public const ROL_ADMIN = 'admin';
    public const ROL_VENDEDOR = 'vendedor';

    public const ROLES = [
        self::ROL_SUPER_ADMIN,
        self::ROL_ADMIN,
        self::ROL_VENDEDOR,
    ];

    public const ETIQUETAS_ROL = [
        self::ROL_SUPER_ADMIN => 'Super administrador',
        self::ROL_ADMIN => 'Administrador',
        self::ROL_VENDEDOR => 'Vendedor',
    ];

    /**
     * Módulos que quedan reservados para administración (super_admin/admin).
     * Un vendedor opera los módulos operativos (productos, inventario,
     * clientes, pedidos, ventas, banners, comunicaciones, plantillas…).
     */
    public const MODULOS_SOLO_ADMIN = [
        'caja',
        'reportes',
        'dashboard',
        'pagos',
        'configuracion',
        'usuarios',
        'cupones',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function etiquetaRol(): string
    {
        return self::ETIQUETAS_ROL[$this->role] ?? $this->role;
    }

    public function esSuperAdmin(): bool
    {
        return $this->role === self::ROL_SUPER_ADMIN;
    }

    public function esAdministracion(): bool
    {
        return in_array($this->role, [self::ROL_SUPER_ADMIN, self::ROL_ADMIN], true);
    }

    /**
     * Indica si el usuario puede operar un módulo del panel.
     */
    public function tieneAcceso(string $modulo): bool
    {
        if (! $this->activo) {
            return false;
        }

        if ($this->esAdministracion()) {
            return true;
        }

        return ! in_array($modulo, self::MODULOS_SOLO_ADMIN, true);
    }
}