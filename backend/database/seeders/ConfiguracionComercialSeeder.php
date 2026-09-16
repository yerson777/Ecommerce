<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use App\Models\MetodoEntrega;
use App\Models\CategoriaGasto;

class ConfiguracionComercialSeeder extends \Illuminate\Database\Seeder
{
    public function run(): void
    {
        $metodosPago = ['Efectivo', 'QR', 'Transferencia', 'Tarjeta'];
        foreach ($metodosPago as $orden => $nombre) {
            MetodoPago::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true, 'orden' => $orden]
            );
        }

        $metodosEntrega = [
            ['nombre' => 'Retiro en tienda', 'costo' => 0, 'orden' => 0],
            ['nombre' => 'Entrega a domicilio', 'costo' => 15, 'orden' => 1],
        ];
        foreach ($metodosEntrega as $datos) {
            MetodoEntrega::query()->updateOrCreate(
                ['nombre' => $datos['nombre']],
                $datos + ['activo' => true]
            );
        }

        $categoriasGasto = ['Transporte', 'Publicidad', 'Embalaje', 'Otros'];
        foreach ($categoriasGasto as $orden => $nombre) {
            CategoriaGasto::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true, 'orden' => $orden]
            );
        }
    }
}