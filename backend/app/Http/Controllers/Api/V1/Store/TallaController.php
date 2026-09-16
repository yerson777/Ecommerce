<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TallaResource;
use App\Models\Talla;
use App\Support\Api;
use Illuminate\Http\Request;

class TallaController extends Controller
{
    public function index(Request $request)
    {
        $tallas = Talla::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->withCount(['productos' => fn ($q) => $q->where('publicado', true)])
            ->get();

        return Api::collection(TallaResource::collection($tallas), 'Listado de tallas.');
    }
}