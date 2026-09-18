<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlantillaMensaje extends Model
{
    use HasFactory;

    protected $table = 'plantillas_mensajes';

    protected $fillable = [
        'clave',
        'nombre',
        'mensaje',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}