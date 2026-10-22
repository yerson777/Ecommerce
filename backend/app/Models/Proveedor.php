<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    public const TIPO_FARDO_COREANO = 'fardo_coreano';

    public const TIPO_FARDO_AMERICANO = 'fardo_americano';

    public const TIPO_OTRO = 'otro';

    public const TIPOS = [
        self::TIPO_FARDO_COREANO,
        self::TIPO_FARDO_AMERICANO,
        self::TIPO_OTRO,
    ];

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'contacto',
        'telefono',
        'ciudad',
        'tipo',
        'nota',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
