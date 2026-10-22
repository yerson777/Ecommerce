<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Categoria\CategoriaMoverRequest;
use App\Http\Requests\V1\Admin\Categoria\CategoriaStoreRequest;
use App\Http\Requests\V1\Admin\Categoria\CategoriaUpdateRequest;
use App\Http\Resources\V1\CategoriaResource;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CatalogoImagenService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoriaController extends Controller
{
    public function __construct(private readonly CatalogoImagenService $imagenes) {}

    public function index(Request $request)
    {
        $categorias = Categoria::query()
            ->with('parent:id,nombre')
            ->withCount(['productos', 'hijos'])
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return Api::collection(CategoriaResource::collection($categorias), 'Listado de categorías.');
    }

    public function store(CategoriaStoreRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['nombre']);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $this->imagenes->guardar($request->file('imagen'), 'categorias');
        } else {
            unset($data['imagen']);
        }

        $categoria = Categoria::create($data);

        return Api::resource(
            new CategoriaResource($categoria->load('parent:id,nombre')),
            'Categoría creada correctamente.',
            201
        );
    }

    public function show(int $id)
    {
        $categoria = Categoria::with('parent:id,nombre')->withCount(['productos', 'hijos'])->findOrFail($id);

        return Api::resource(new CategoriaResource($categoria), 'Detalle de la categoría.');
    }

    public function update(CategoriaUpdateRequest $request, int $id)
    {
        $categoria = Categoria::findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->validarPadre($categoria, (int) $data['parent_id']);
        }

        // El slug es inmutable: si no se envía explícitamente se conserva el actual.
        if (array_key_exists('slug', $data) && blank($data['slug'])) {
            unset($data['slug']);
        }

        if ($request->hasFile('imagen')) {
            $anterior = $categoria->imagen;
            $data['imagen'] = $this->imagenes->guardar($request->file('imagen'), 'categorias');
        } else {
            unset($data['imagen']);
        }

        $categoria->update($data);

        if (isset($data['imagen']) && ! empty($anterior)) {
            $this->imagenes->eliminar($anterior);
        }

        return Api::resource(
            new CategoriaResource($categoria->fresh()->load('parent:id,nombre')),
            'Categoría actualizada correctamente.'
        );
    }

    public function destroy(int $id)
    {
        $categoria = Categoria::findOrFail($id);

        if ($categoria->productos()->exists()) {
            return Api::error('No se puede eliminar la categoría porque tiene productos asociados.', 409);
        }

        if ($categoria->hijos()->exists()) {
            return Api::error('No se puede eliminar la categoría porque tiene subcategorías asociadas.', 409);
        }

        $anterior = $categoria->imagen;
        $categoria->delete();
        $this->imagenes->eliminar($anterior);

        return Api::noContent('Categoría eliminada correctamente.');
    }

    /**
     * Reasigna todas las prendas de una categoría a otra, útil antes de borrarla.
     */
    public function moverProductos(CategoriaMoverRequest $request, int $id)
    {
        $categoria = Categoria::findOrFail($id);
        $destino = Categoria::findOrFail($request->integer('categoria_id'));

        if ($destino->id === $categoria->id) {
            return Api::error('La categoría destino debe ser distinta a la de origen.', 422);
        }

        $movidos = Producto::where('categoria_id', $categoria->id)->update(['categoria_id' => $destino->id]);

        return Api::success([
            'movidos' => $movidos,
        ], "Se reasignaron {$movidos} prendas a {$destino->nombre}.");
    }

    private function validarPadre(Categoria $categoria, int $padreId): void
    {
        if ($padreId === $categoria->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Una categoría no puede ser su propia categoría superior.',
            ]);
        }

        if (in_array($padreId, $this->descendientesIds($categoria->id), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'No se puede asignar como superior una subcategoría de esta categoría.',
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function descendientesIds(int $id): array
    {
        $ids = [];
        $frontera = [$id];

        while (! empty($frontera)) {
            $hijos = Categoria::whereIn('parent_id', $frontera)->pluck('id')->all();
            $ids = array_merge($ids, $hijos);
            $frontera = $hijos;
        }

        return $ids;
    }
}
