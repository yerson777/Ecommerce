<?php

namespace App\Services;

use App\Models\Cupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuponService
{
    /**
     * Valida un cupón sin gastar el contador de usos.
     *
     * @return array{cupon: Cupon, descuento: float}
     */
    public function validar(string $codigo, float $subtotal): array
    {
        $cupon = Cupon::query()
            ->where('codigo', mb_strtoupper(trim($codigo)))
            ->first();

        return $this->calcular($cupon, $subtotal);
    }

    /**
     * Valida un cupón y, si es válido, incrementa el contador de usos
     * dentro de la transacción con bloqueo de fila.
     *
     * @return array{cupon: Cupon, descuento: float}
     */
    public function aplicar(string $codigo, float $subtotal): array
    {
        return DB::transaction(function () use ($codigo, $subtotal) {
            /** @var Cupon|null $cupon */
            $cupon = Cupon::query()
                ->where('codigo', mb_strtoupper(trim($codigo)))
                ->lockForUpdate()
                ->first();

            $resultado = $this->calcular($cupon, $subtotal);

            $resultado['cupon']->increment('usos');

            return $resultado;
        });
    }

    /**
     * @return array{cupon: Cupon, descuento: float}
     */
    private function calcular(?Cupon $cupon, float $subtotal): array
    {
        if (! $cupon) {
            throw $this->invalido('El código de cupón no es válido.');
        }

        if (! $cupon->activo) {
            throw $this->invalido('Este cupón no está activo.');
        }

        if ($cupon->vencido()) {
            throw $this->invalido('Este cupón ya venció.');
        }

        if ($cupon->agotado()) {
            throw $this->invalido('Este cupón ya agotó sus usos disponibles.');
        }

        if ($cupon->minimo_compra !== null && $subtotal < (float) $cupon->minimo_compra) {
            throw $this->invalido(
                'Este cupón requiere una compra mínima de Bs '
                . number_format((float) $cupon->minimo_compra, 2, '.', '')
                . '.'
            );
        }

        $descuento = match ($cupon->tipo) {
            Cupon::TIPO_PORCENTAJE => min(round($subtotal * ((float) $cupon->valor / 100), 2), $subtotal),
            default => min((float) $cupon->valor, $subtotal),
        };

        return ['cupon' => $cupon, 'descuento' => $descuento];
    }

    private function invalido(string $mensaje): ValidationException
    {
        return ValidationException::withMessages([
            'cupon_codigo' => [$mensaje],
        ]);
    }
}