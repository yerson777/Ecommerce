<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DespublicarPrendasVendidas extends Command
{
    protected $signature = 'prendas:despublicar-vendidas
                            {--apply : Aplica los cambios (por defecto solo simula)}';

    protected $description = 'Despublica prendas vendidas que quedaron con publicado=true (corrige datos historicos).';

    public function handle(): int
    {
        $base = fn () => Producto::query()
            ->where('estado', Producto::ESTADO_VENDIDA)
            ->where('publicado', true);

        $total = $base()->count();

        if ($total === 0) {
            $this->info('No hay prendas vendidas publicadas. Nada que hacer.');

            return self::SUCCESS;
        }

        $this->line("Prendas vendidas publicadas: {$total}");
        foreach ($base()->orderBy('codigo')->pluck('codigo') as $codigo) {
            $this->line(" - {$codigo}");
        }

        if (! $this->option('apply')) {
            $this->warn('Modo simulacion (dry-run). Ejecuta con --apply para despublicarlas.');

            return self::SUCCESS;
        }

        $afectadas = DB::transaction(fn () => $base()->update(['publicado' => false]));

        $this->info("Prendas despublicadas: {$afectadas}");

        return self::SUCCESS;
    }
}
