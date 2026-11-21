<?php

namespace App\Console\Commands;

use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VincularPagosAVentas extends Command
{
    protected $signature = 'pagos:vincular-ventas
                            {--apply : Aplica los cambios; sin esta opción solo simula}';

    protected $description = 'Vincula pagos huérfanos (venta_id NULL) a la única venta de su pedido';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');

        $pagos = Pago::query()
            ->with(['pedido' => fn ($q) => $q->withCount('venta')->with('venta')])
            ->whereNull('venta_id')
            ->orderBy('id')
            ->get();

        [$vinculables, $omitidos] = $this->clasificar($pagos);

        $this->info('Modo: '.($aplicar ? 'APLICAR' : 'DRY-RUN (sin cambios)'));
        $this->line('Pagos con venta_id NULL revisados: '.$pagos->count());
        $this->line('Pagos a vincular: '.$vinculables->count());
        $this->line('Pagos omitidos para revisión manual: '.$omitidos->count());

        if ($vinculables->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Pago', 'Pedido', 'Monto', 'Estado', 'Venta destino', 'Acción'],
                $vinculables->map(fn (Pago $pago) => [
                    $pago->id,
                    $pago->pedido_id,
                    $pago->monto,
                    $pago->estado,
                    $pago->pedido?->venta?->numero_venta.' (#'.$pago->pedido?->venta?->id.')',
                    $aplicar ? 'Vincular' : 'Vincularía',
                ])->all()
            );
        }

        if ($omitidos->isNotEmpty()) {
            $this->newLine();
            $this->warn('Omitidos para revisión manual:');
            $this->table(
                ['Pago', 'Pedido', 'Monto', 'Estado', 'Motivo'],
                $omitidos->map(fn (array $fila) => [
                    $fila['pago']->id,
                    $fila['pago']->pedido_id ?? '—',
                    $fila['pago']->monto,
                    $fila['pago']->estado,
                    $fila['motivo'],
                ])->all()
            );
        }

        if (! $aplicar) {
            $this->newLine();
            $this->warn('DRY-RUN: no se modificó ningún pago. Ejecuta con --apply para aplicar.');

            return self::SUCCESS;
        }

        $total = DB::transaction(function () use ($vinculables): int {
            $total = 0;

            foreach ($vinculables as $pago) {
                $ventas = Venta::query()
                    ->where('pedido_id', $pago->pedido_id)
                    ->pluck('id');

                if ($ventas->count() !== 1) {
                    continue;
                }

                $total += Pago::query()
                    ->whereKey($pago->id)
                    ->whereNull('venta_id')
                    ->update(['venta_id' => $ventas->first()]);
            }

            return $total;
        });

        $this->newLine();
        $this->info("Pagos vinculados: {$total}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Pago>  $pagos
     * @return array{0: Collection<int, Pago>, 1: Collection<int, array{pago: Pago, motivo: string}>}
     */
    private function clasificar(Collection $pagos): array
    {
        $vinculables = collect();
        $omitidos = collect();

        foreach ($pagos as $pago) {
            $pedido = $pago->pedido;

            if (! $pedido) {
                $omitidos->push([
                    'pago' => $pago,
                    'motivo' => 'Sin pedido asociado',
                ]);
                continue;
            }

            $cantidad = (int) $pedido->venta_count;

            if ($cantidad === 0) {
                $omitidos->push([
                    'pago' => $pago,
                    'motivo' => 'El pedido no tiene ventas',
                ]);
                continue;
            }

            if ($cantidad > 1) {
                $omitidos->push([
                    'pago' => $pago,
                    'motivo' => 'El pedido tiene más de una venta',
                ]);
                continue;
            }

            $vinculables->push($pago);
        }

        return [$vinculables, $omitidos];
    }
}
