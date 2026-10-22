<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;

class ProveedorService
{
    /**
     * Listado con los agregados que muestra la UI: total comprado (sin
     * contar compras anuladas) y cantidad de prendas asociadas.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros): Collection
    {
        return Proveedor::query()
            ->withCount('compras')
            ->when($this->texto($filtros, 'busqueda'), function ($q, $busqueda) {
                $q->where(function ($query) use ($busqueda) {
                    $query->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('contacto', 'like', "%{$busqueda}%")
                        ->orWhere('telefono', 'like', "%{$busqueda}%")
                        ->orWhere('ciudad', 'like', "%{$busqueda}%");
                });
            })
            ->when($this->texto($filtros, 'ciudad'), fn ($q, $ciudad) => $q->where('ciudad', $ciudad))
            ->when($this->texto($filtros, 'tipo'), fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when(array_key_exists('activo', $filtros) && $filtros['activo'] !== null && $filtros['activo'] !== '', function ($q) use ($filtros) {
                $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
            })
            ->withSum(
                ['compras as total_comprado' => fn ($q) => $q->where('estado', Compra::ESTADO_ACTIVA)],
                'costo_total'
            )
            ->withCount('productos as prendas_count')
            ->orderBy('nombre')
            ->get();
    }

    public function ficha(Proveedor $proveedor): Proveedor
    {
        $proveedor->load([
            'compras' => fn ($q) => $q->withCount('compraProductos as prendas_count')
                ->orderByDesc('fecha')
                ->orderByDesc('id'),
        ]);

        $proveedor->loadCount('productos as prendas_count');

        $proveedor->setAttribute(
            'total_comprado',
            $proveedor->compras
                ->where('estado', Compra::ESTADO_ACTIVA)
                ->sum(fn ($compra) => (float) $compra->costo_total)
        );

        $proveedor->setAttribute(
            'compras_count',
            $proveedor->compras->count()
        );

        return $proveedor;
    }

    public function estaReferenciado(Proveedor $proveedor): bool
    {
        return $proveedor->compras()->exists() || $proveedor->productos()->exists();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function texto(array $filtros, string $clave): ?string
    {
        $valor = $filtros[$clave] ?? null;

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return trim($valor);
    }
}
