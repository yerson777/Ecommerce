<?php

namespace App\Services;

use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\Pedido;
use App\Models\Producto;
use App\Support\Contacto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuponService
{
    /**
     * Valida un cupón sin gastar el contador de usos.
     *
     * @param  array<int, Producto>  $productos  Prendas del carrito/pedido.
     * @param  array{subtotal?:float|null,telefono?:string|null,email?:string|null}  $contexto
     * @return array{cupon: Cupon, descuento: float}
     */
    public function validar(string $codigo, array $productos = [], array $contexto = []): array
    {
        $cupon = Cupon::query()
            ->where('codigo', mb_strtoupper(trim($codigo)))
            ->with('categorias')
            ->first();

        return $this->calcular(
            $cupon,
            $productos,
            (float) ($contexto['subtotal'] ?? 0),
            $contexto['telefono'] ?? null,
            $contexto['email'] ?? null,
            $contexto['precios_efectivos'] ?? [],
        );
    }

    /**
     * Valida un cupón y, si es válido, incrementa el contador de usos y
     * registra el uso dentro de la transacción con bloqueo de fila.
     *
     * El uso se crea sin pedido asociado; el llamador debe enlazarlo con
     * {@see vincularUso()} una vez creado el pedido.
     *
     * @param  array<int, Producto>  $productos
     * @param  array{subtotal?:float|null,telefono?:string|null,email?:string|null,cliente_id?:int|null}  $contexto
     * @return array{cupon: Cupon, descuento: float, uso: CuponUso}
     */
    public function aplicar(string $codigo, array $productos = [], array $contexto = []): array
    {
        return DB::transaction(function () use ($codigo, $productos, $contexto) {
            /** @var Cupon|null $cupon */
            $cupon = Cupon::query()
                ->where('codigo', mb_strtoupper(trim($codigo)))
                ->with('categorias')
                ->lockForUpdate()
                ->first();

            $resultado = $this->calcular(
                $cupon,
                $productos,
                (float) ($contexto['subtotal'] ?? 0),
                $contexto['telefono'] ?? null,
                $contexto['email'] ?? null,
                $contexto['precios_efectivos'] ?? [],
            );

            /** @var Cupon $cupon */
            $cupon = $resultado['cupon'];

            $uso = CuponUso::create([
                'cupon_id' => $cupon->id,
                'pedido_id' => null,
                'cliente_id' => $contexto['cliente_id'] ?? null,
                'telefono_normalizado' => Contacto::normalizarTelefono($contexto['telefono'] ?? null),
                'email_normalizado' => Contacto::normalizarEmail($contexto['email'] ?? null),
                'monto_descontado' => $resultado['descuento'],
            ]);

            $cupon->increment('usos');

            return ['cupon' => $cupon, 'descuento' => $resultado['descuento'], 'uso' => $uso];
        });
    }

    /**
     * Enlaza el uso registrado con el pedido ya creado.
     */
    public function vincularUso(CuponUso $uso, Pedido $pedido): void
    {
        $uso->forceFill([
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
        ])->save();
    }

    /**
     * Libera el uso de un cupón al cancelar o devolver un pedido: elimina el
     * registro de uso y decrementa el contador (sin bajar de cero).
     */
    public function liberar(Pedido $pedido): void
    {
        $uso = CuponUso::query()->where('pedido_id', $pedido->id)->first();

        if (! $uso) {
            return;
        }

        /** @var Cupon|null $cupon */
        $cupon = Cupon::query()->whereKey($uso->cupon_id)->lockForUpdate()->first();

        $uso->delete();

        if ($cupon && (int) $cupon->usos > 0) {
            $cupon->decrement('usos');
        }
    }

    /**
     * @param  array<int, Producto>  $productos
     * @param  array<int, float>  $preciosEfectivos  Precio ya con oferta por prenda; los que no aparecen usan el precio base.
     * @return array{cupon: Cupon, descuento: float}
     */
    private function calcular(
        ?Cupon $cupon,
        array $productos,
        float $subtotal,
        ?string $telefono,
        ?string $email,
        array $preciosEfectivos = [],
    ): array {
        if (! $cupon) {
            throw $this->invalido('El código de cupón no es válido.');
        }

        if (! $cupon->activo) {
            throw $this->invalido('Este cupón no está activo.');
        }

        if ($cupon->noIniciado()) {
            throw $this->invalido(
                'Este cupón estará vigente desde el '
                .$cupon->fecha_inicio?->format('d/m/Y')
                .'.'
            );
        }

        if ($cupon->vencido()) {
            throw $this->invalido('Este cupón ya venció.');
        }

        if ($cupon->agotado()) {
            throw $this->invalido('Este cupón ya agotó sus usos disponibles.');
        }

        if ($cupon->limite_por_cliente !== null && $this->usosDelCliente($cupon, $telefono, $email) >= (int) $cupon->limite_por_cliente) {
            throw $this->invalido('Ya alcanzaste el límite de usos de este cupón.');
        }

        $subtotalElegible = $this->subtotalElegible($cupon, $productos, $subtotal, $preciosEfectivos);

        if ($cupon->minimo_compra !== null && $subtotal < (float) $cupon->minimo_compra) {
            throw $this->invalido(
                'Este cupón requiere una compra mínima de Bs '
                .number_format((float) $cupon->minimo_compra, 2, '.', '')
                .'.'
            );
        }

        if ($subtotalElegible <= 0) {
            throw $this->invalido('Este cupón no aplica a las prendas de tu pedido.');
        }

        $descuento = match ($cupon->tipo) {
            Cupon::TIPO_PORCENTAJE => min(round($subtotalElegible * ((float) $cupon->valor / 100), 2), $subtotalElegible),
            default => min((float) $cupon->valor, $subtotalElegible),
        };

        return ['cupon' => $cupon, 'descuento' => $descuento];
    }

    /**
     * Subtotal sobre el que aplica el descuento. Si el cupón no limita
     * categorías, es el subtotal completo; si limita, solo la suma de las
     * prendas de las categorías permitidas. Cuando el llamador envía precios
     * ya con oferta, se usan esos en lugar del precio base.
     *
     * @param  array<int, Producto>  $productos
     * @param  array<int, float>  $preciosEfectivos
     */
    private function subtotalElegible(Cupon $cupon, array $productos, float $subtotal, array $preciosEfectivos = []): float
    {
        if (! $cupon->relationLoaded('categorias')) {
            $cupon->load('categorias');
        }

        $categoriasIds = $cupon->categorias->pluck('id')->all();

        if (empty($categoriasIds)) {
            return $subtotal;
        }

        $suma = 0.0;
        foreach ($productos as $producto) {
            if (in_array($producto->categoria_id, $categoriasIds, false)) {
                $suma += $preciosEfectivos[$producto->id] ?? (float) $producto->precio;
            }
        }

        return round($suma, 2);
    }

    private function usosDelCliente(Cupon $cupon, ?string $telefono, ?string $email): int
    {
        $tel = Contacto::normalizarTelefono($telefono);
        $mail = Contacto::normalizarEmail($email);

        if (! $tel && ! $mail) {
            return 0;
        }

        return CuponUso::query()
            ->where('cupon_id', $cupon->id)
            ->where(function ($q) use ($tel, $mail) {
                if ($tel) {
                    $q->orWhere('telefono_normalizado', $tel);
                }
                if ($mail) {
                    $q->orWhere('email_normalizado', $mail);
                }
            })
            ->count();
    }

    private function invalido(string $mensaje): ValidationException
    {
        return ValidationException::withMessages([
            'cupon_codigo' => [$mensaje],
        ]);
    }
}
