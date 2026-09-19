<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pedido extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_CANCELADO = 'cancelado';
    public const ESTADO_COMPLETADO = 'completado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADO,
        self::ESTADO_CANCELADO,
        self::ESTADO_COMPLETADO,
    ];

    /**
     * Transiciones de estado permitidas. La máquina de estados queda
     * centralizada aquí para poder ampliarse con nuevos estados sin
     * tocar controladores ni servicios.
     */
    public const TRANSICIONES = [
        self::ESTADO_PENDIENTE => [self::ESTADO_CONFIRMADO, self::ESTADO_CANCELADO],
        self::ESTADO_CONFIRMADO => [self::ESTADO_CANCELADO, self::ESTADO_COMPLETADO],
        self::ESTADO_CANCELADO => [],
        self::ESTADO_COMPLETADO => [],
    ];

    protected $table = 'pedidos';

    protected $fillable = [
        'numero_pedido',
        'cliente_id',
        'metodo_pago_id',
        'metodo_entrega_id',
        'estado',
        'subtotal',
        'costo_envio',
        'total',
        'fecha_pedido',
        'notas',
        'comprobante_path',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_pedido' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function metodoEntrega(): BelongsTo
    {
        return $this->belongsTo(MetodoEntrega::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function puedeTransicionarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    public function estadosSiguientes(): array
    {
        return self::TRANSICIONES[$this->estado] ?? [];
    }

    /**
     * Estado de pago del pedido, independiente del estado del pedido.
     * Se deriva de la suma de pagos confirmados ("completado"):
     * pendiente, parcial, pagado o cancelado (si el pedido está cancelado).
     */
    public function estadoPago(): string
    {
        if ($this->estado === self::ESTADO_CANCELADO) {
            return 'cancelado';
        }

        $total = (float) $this->total;
        $pagado = isset($this->pagos_completados_total)
            ? (float) $this->pagos_completados_total
            : (float) $this->pagos->where('estado', Pago::ESTADO_COMPLETADO)->sum('monto');

        if ($pagado <= 0) {
            return 'pendiente';
        }

        return $pagado >= $total - 0.01 ? 'pagado' : 'parcial';
    }

    public function totalPagado(): string
    {
        $pagado = isset($this->pagos_completados_total)
            ? (float) $this->pagos_completados_total
            : (float) $this->pagos->where('estado', Pago::ESTADO_COMPLETADO)->sum('monto');

        return number_format($pagado, 2);
    }

    public function saldoPendiente(): string
    {
        $pagado = (float) $this->totalPagado();

        return number_format(max(0.0, (float) $this->total - $pagado), 2);
    }
}