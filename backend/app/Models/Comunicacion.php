<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comunicacion extends Model
{
    use HasFactory;

    public const TIPO_WHATSAPP = 'whatsapp';
    public const TIPO_EMAIL = 'email';
    public const TIPO_OTRO = 'otro';

    public const TIPOS = [
        self::TIPO_WHATSAPP,
        self::TIPO_EMAIL,
        self::TIPO_OTRO,
    ];

    /**
     * Estado "iniciada": el mensaje fue generado y se intentó abrir WhatsApp.
     * NUNCA se asume la entrega real del mensaje solo porque se abrió un enlace.
     */
    public const ESTADO_INICIADA = 'iniciada';

    protected $table = 'comunicaciones';

    protected $fillable = [
        'pedido_id',
        'cliente_id',
        'user_id',
        'tipo',
        'mensaje',
        'estado',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}