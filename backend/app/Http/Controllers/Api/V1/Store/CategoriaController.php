<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoriaResource;
use App\Models\Categoria;
use App\Support\Api;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $categorias = Categoria::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->withCount(['productos' => fn ($q) => $q->where('publicado', true)])
            ->get();

        return Api::collection(CategoriaResource::collection($categorias), 'Listado de categorías.');
    }
}