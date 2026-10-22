<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraProducto;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraService
{
    /**
     * Crea la compra (lote) y sincroniza sus prendas con el costo asignado.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Compra
    {
        return DB::transaction(function () use ($datos) {
            $compra = Compra::create([
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'fecha' => $datos['fecha'],
                'costo_total' => (float) ($datos['costo_total'] ?? 0),
                'gastos_extra' => (float) ($datos['gastos_extra'] ?? 0),
                'prendas_total' => (int) ($datos['prendas_total'] ?? 0),
                'prendas_descartadas' => (int) ($datos['prendas_descartadas'] ?? 0),
                'nota' => $datos['nota'] ?? null,
                'estado' => Compra::ESTADO_ACTIVA,
            ]);

            $this->sincronizarPrendas($compra, $datos['productos'] ?? []);

            return $compra->fresh(['proveedor', 'compraProductos.producto.talla', 'compraProductos.producto.imagenes']);
        });
    }

    /**
     * Edita una compra. Recalcula el costo de sus prendas, pero las prendas
     * ya vendidas conservan el costo con el que se vendieron.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Compra $compra, array $datos): Compra
    {
        if ($compra->estaAnulada()) {
            throw ValidationException::withMessages([
                'estado' => ['Una compra anulada no se puede editar.'],
            ]);
        }

        return DB::transaction(function () use ($compra, $datos) {
            $compra->update([
                'proveedor_id' => $datos['proveedor_id'] ?? $compra->proveedor_id,
                'fecha' => $datos['fecha'] ?? $compra->fecha,
                'costo_total' => (float) ($datos['costo_total'] ?? $compra->costo_total),
                'gastos_extra' => (float) ($datos['gastos_extra'] ?? $compra->gastos_extra),
                'prendas_total' => array_key_exists('prendas_total', $datos)
                    ? (int) $datos['prendas_total']
                    : $compra->prendas_total,
                'prendas_descartadas' => array_key_exists('prendas_descartadas', $datos)
                    ? (int) $datos['prendas_descartadas']
                    : $compra->prendas_descartadas,
                'nota' => $datos['nota'] ?? $compra->nota,
            ]);

            if (array_key_exists('productos', $datos)) {
                $this->sincronizarPrendas($compra, $datos['productos']);
            } else {
                // Aunque no cambien las prendas, si cambió el costo del lote
                // hay que volver a repartirlo entre las prendas no vendidas.
                $this->reasignarCostos($compra);
            }

            return $compra->fresh(['proveedor', 'compraProductos.producto.talla', 'compraProductos.producto.imagenes']);
        });
    }

    /**
     * Reparte el costo del lote entre las prendas y guarda en cada prenda su
     * costo (productos.costo) para calcular su ganancia.
     *
     * @param  array<int|string, mixed>  $productos  Lista de {producto_id, costo_asignado?}
     */
    private function sincronizarPrendas(Compra $compra, array $productos): void
    {
        // Normaliza la lista a {producto_id => costo_asignado|null}.
        $filas = collect($productos)
            ->mapWithKeys(function ($fila) {
                $id = (int) (is_array($fila) ? ($fila['producto_id'] ?? $fila) : $fila);
                $manual = is_array($fila) ? ($fila['costo_asignado'] ?? null) : null;

                return [$id => $manual !== null ? (float) $manual : null];
            });

        $ids = $filas->keys()->unique()->values();

        // Solo se pueden asignar prendas que existan.
        $existentes = Producto::query()->whereIn('id', $ids)->pluck('id')->all();
        $faltantes = $ids->diff($existentes)->values();

        if ($faltantes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'productos' => $faltantes
                    ->map(fn ($id) => "La prenda #{$id} no existe.")
                    ->all(),
            ]);
        }

        // Quita del lote las prendas que ya no están (solo si no se vendieron).
        $aQuitar = CompraProducto::query()
            ->where('compra_id', $compra->id)
            ->whereNotIn('producto_id', $ids)
            ->get();

        foreach ($aQuitar as $vinculo) {
            $producto = Producto::query()->find($vinculo->producto_id);

            if ($producto && $producto->estado === Producto::ESTADO_VENDIDA) {
                throw ValidationException::withMessages([
                    'productos' => ["La prenda {$producto->nombre} ya se vendió; no se puede quitar del lote."],
                ]);
            }

            $vinculo->delete();

            if ($producto && (int) $producto->compra_id === (int) $compra->id) {
                $producto->forceFill(['compra_id' => null])->save();
            }
        }

        // Crea/actualiza los vínculos, conservando el costo manual si viene.
        foreach ($ids as $productoId) {
            $vinculo = CompraProducto::query()->firstOrNew([
                'compra_id' => $compra->id,
                'producto_id' => $productoId,
            ]);

            $manual = $filas->get($productoId);
            $vinculo->costo_asignado = $manual !== null ? $manual : (float) $vinculo->costo_asignado;

            $vinculo->save();
        }

        $this->reasignarCostos($compra);
    }

    /**
     * Calcula y persiste el costo de cada prenda del lote:
     *  - Si viene "costo_asignado" explícito, se usa ese.
     *  - Si no, se reparte el costo del lote en partes iguales.
     * Las prendas ya vendidas no se tocan (conservan su costo histórico).
     */
    private function reasignarCostos(Compra $compra): void
    {
        $vinculos = CompraProducto::query()
            ->where('compra_id', $compra->id)
            ->get();

        if ($vinculos->isEmpty()) {
            return;
        }

        $manuales = $vinculos->filter(fn (CompraProducto $v) => (float) $v->costo_asignado > 0);

        $total = $compra->costoRepartible();
        $cantidad = $vinculos->count();

        // Las prendas descartadas no cuentan: el costo del fardo se reparte
        // solo entre las prendas vendibles (total - descartadas).
        $divisor = $compra->prendasVendibles($cantidad);

        // Si ninguna tiene costo manual, reparto igualitario.
        if ($manuales->isEmpty()) {
            $porPrenda = $divisor > 0 ? round($total / $divisor, 2) : 0.0;

            foreach ($vinculos as $vinculo) {
                $this->guardarCosto($vinculo, $porPrenda);
            }

            return;
        }

        // Reparto mixto: las manuales conservan su valor; el resto se reparte
        // con lo que sobra.
        $sumaManual = (float) $manuales->sum(fn (CompraProducto $v) => (float) $v->costo_asignado);
        $restantes = $vinculos->reject(fn (CompraProducto $v) => $manuales->contains('id', $v->id));
        $sobrante = max(0.0, round($total - $sumaManual, 2));
        $porPrenda = $restantes->isNotEmpty() ? round($sobrante / $restantes->count(), 2) : 0.0;

        foreach ($manuales as $vinculo) {
            $this->guardarCosto($vinculo, (float) $vinculo->costo_asignado);
        }

        foreach ($restantes as $vinculo) {
            $this->guardarCosto($vinculo, $porPrenda);
        }
    }

    private function guardarCosto(CompraProducto $vinculo, float $costo): void
    {
        /** @var Producto|null $producto */
        $producto = Producto::query()->find($vinculo->producto_id);

        if (! $producto) {
            return;
        }

        // Una prenda vendida conserva el costo con el que se vendió.
        if ($producto->estado === Producto::ESTADO_VENDIDA) {
            return;
        }

        $vinculo->costo_asignado = $costo;
        $vinculo->save();

        $producto->forceFill([
            'costo' => $costo,
            'proveedor_id' => $vinculo->compra->proveedor_id,
            'compra_id' => $vinculo->compra_id,
        ])->save();
    }

    /**
     * Anula una compra. No permite anular si tiene prendas vendidas.
     */
    public function anular(Compra $compra): Compra
    {
        if ($compra->estaAnulada()) {
            throw ValidationException::withMessages([
                'estado' => ['La compra ya está anulada.'],
            ]);
        }

        $vendidas = $this->prendasVendidas($compra);

        if ($vendidas > 0) {
            throw ValidationException::withMessages([
                'estado' => ['La compra tiene prendas vendidas; no se puede anular.'],
            ]);
        }

        return DB::transaction(function () use ($compra) {
            $compra->update([
                'estado' => Compra::ESTADO_ANULADA,
                'anulada_en' => now(),
            ]);

            // Desvincula las prendas del lote anulado.
            CompraProducto::query()
                ->where('compra_id', $compra->id)
                ->get()
                ->each(function (CompraProducto $vinculo) use ($compra) {
                    $producto = Producto::query()->find($vinculo->producto_id);
                    if ($producto && (int) $producto->compra_id === (int) $compra->id) {
                        $producto->forceFill(['compra_id' => null])->save();
                    }
                });

            return $compra->fresh(['proveedor', 'compraProductos.producto']);
        });
    }

    public function puedeEliminar(Compra $compra): bool
    {
        return $this->prendasVendidas($compra) === 0;
    }

    public function eliminar(Compra $compra): void
    {
        if (! $this->puedeEliminar($compra)) {
            throw ValidationException::withMessages([
                'estado' => ['La compra tiene prendas vendidas; solo puede anularse.'],
            ]);
        }

        DB::transaction(function () use ($compra) {
            CompraProducto::query()
                ->where('compra_id', $compra->id)
                ->get()
                ->each(function (CompraProducto $vinculo) use ($compra) {
                    $producto = Producto::query()->find($vinculo->producto_id);
                    if ($producto && (int) $producto->compra_id === (int) $compra->id) {
                        $producto->forceFill(['compra_id' => null])->save();
                    }
                });

            $compra->delete();
        });
    }

    /**
     * Estadísticas del lote: prendas totales, vendidas, recuperado (suma real
     * cobrada en ventas no anuladas) y cuánto falta recuperar.
     *
     * @return array<string, mixed>
     */
    public function estadisticas(Compra $compra): array
    {
        $productoIds = CompraProducto::query()
            ->where('compra_id', $compra->id)
            ->pluck('producto_id')
            ->all();

        $prendas = count($productoIds);
        $vendidas = $this->prendasVendidas($compra, $productoIds);

        $recuperado = 0.0;
        if (! empty($productoIds)) {
            $recuperado = (float) DB::table('venta_items')
                ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
                ->whereNull('ventas.anulada_en')
                ->whereIn('venta_items.producto_id', $productoIds)
                ->sum('venta_items.precio_unitario');
        }

        $costo = $compra->costoRepartible();

        return [
            'prendas_count' => $prendas,
            'vendidas_count' => $vendidas,
            'recuperado' => round($recuperado, 2),
            'falta_recuperar' => round(max(0.0, $costo - $recuperado), 2),
        ];
    }

    /**
     * @param  int[]|null  $productoIds
     */
    private function prendasVendidas(Compra $compra, ?array $productoIds = null): int
    {
        $productoIds ??= CompraProducto::query()
            ->where('compra_id', $compra->id)
            ->pluck('producto_id')
            ->all();

        if (empty($productoIds)) {
            return 0;
        }

        return Producto::query()
            ->whereIn('id', $productoIds)
            ->where('estado', Producto::ESTADO_VENDIDA)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Compra::query()
            ->with('proveedor')
            ->withCount('compraProductos as prendas_count')
            ->when($this->texto($filtros, 'fecha_desde'), fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($this->texto($filtros, 'fecha_hasta'), fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when($this->texto($filtros, 'proveedor_id'), fn ($q, $pid) => $q->where('proveedor_id', (int) $pid))
            ->when($this->texto($filtros, 'estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate((int) ($filtros['per_page'] ?? 15));
    }

    private function texto(array $filtros, string $clave): ?string
    {
        $valor = $filtros[$clave] ?? null;

        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return null;
        }

        return trim((string) $valor);
    }

    /**
     * @return Collection<int, Compra>
     */
    public function paraExportar(array $filtros): Collection
    {
        return Compra::query()
            ->with('proveedor')
            ->withCount('compraProductos as prendas_count')
            ->when($this->texto($filtros, 'fecha_desde'), fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($this->texto($filtros, 'fecha_hasta'), fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when($this->texto($filtros, 'proveedor_id'), fn ($q, $pid) => $q->where('proveedor_id', (int) $pid))
            ->when($this->texto($filtros, 'estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Proveedores con agregados para la ficha de un proveedor (historial).
     *
     * @return Collection<int, Compra>
     */
    public function comprasDe(Proveedor $proveedor): Collection
    {
        return Compra::query()
            ->where('proveedor_id', $proveedor->id)
            ->withCount('compraProductos as prendas_count')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }
}
