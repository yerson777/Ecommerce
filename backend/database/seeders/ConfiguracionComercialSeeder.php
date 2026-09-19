<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use App\Models\MetodoEntrega;
use App\Models\CategoriaGasto;

class ConfiguracionComercialSeeder extends \Illuminate\Database\Seeder
{
    public function run(): void
    {
        $metodosPago = ['QR', 'Transferencia'];
        foreach ($metodosPago as $orden => $nombre) {
            MetodoPago::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true, 'orden' => $orden]
            );
        }

        $nombresPago = array_values($metodosPago);
        MetodoPago::query()
            ->whereNotIn('nombre', $nombresPago)
            ->update(['activo' => false]);

        $metodosEntrega = [
            ['nombre' => 'Retiro presencial (plaza Corazonistas)', 'costo' => 0, 'orden' => 0],
            ['nombre' => 'Envío por Yando desde la plaza Corazonistas', 'costo' => 0, 'orden' => 1],
            ['nombre' => 'Paquetería Flash Store', 'costo' => 0, 'orden' => 2],
            ['nombre' => 'Envío Provincial o Departamental', 'costo' => 0, 'orden' => 3],
        ];
        foreach ($metodosEntrega as $datos) {
            MetodoEntrega::query()->updateOrCreate(
                ['nombre' => $datos['nombre']],
                $datos + ['activo' => true]
            );
        }

        $nombresEntrega = array_column($metodosEntrega, 'nombre');
        MetodoEntrega::query()
            ->whereNotIn('nombre', $nombresEntrega)
            ->update(['activo' => false]);

        $categoriasGasto = ['Transporte', 'Publicidad', 'Embalaje', 'Otros'];
        foreach ($categoriasGasto as $orden => $nombre) {
            CategoriaGasto::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true, 'orden' => $orden]
            );
        }
    }
}