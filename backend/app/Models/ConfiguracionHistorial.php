<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionHistorial extends Model
{
    use HasFactory;

    protected $table = 'configuracion_historial';

    protected $fillable = [
        'campo',
        'metodo_pago_id',
        'metodo_pago_nombre',
        'valor_anterior',
        'valor_nuevo',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }
}
