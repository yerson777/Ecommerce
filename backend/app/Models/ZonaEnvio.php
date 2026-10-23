<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZonaEnvio extends Model
{
    use HasFactory;

    protected $table = 'zonas_envio';

    protected $fillable = [
        'nombre',
        'departamento',
        'costo',
        'tiempo_estimado',
        'envio_gratis_desde',
        'costo_por_confirmar',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'envio_gratis_desde' => 'decimal:2',
            'costo_por_confirmar' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function aplicaEnvioGratis(float $base): bool
    {
        if ($this->costo_por_confirmar || $this->envio_gratis_desde === null) {
            return false;
        }

        return $base >= (float) $this->envio_gratis_desde;
    }
}
