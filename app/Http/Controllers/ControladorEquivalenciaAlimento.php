<?php

namespace App\Http\Controllers;

use App\Models\EquivalenciaAlimento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ControladorEquivalenciaAlimento extends Controller
{
    public function index()
    {
        return response()->json(EquivalenciaAlimento::all());
    }

    public function store(Request $request)
    {
        $equiv = EquivalenciaAlimento::create($request->all());
        return response()->json($equiv, 201);
    }

    public function show($id)
    {
        return response()->json(EquivalenciaAlimento::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $equiv = EquivalenciaAlimento::findOrFail($id);
        $equiv->update($request->all());
        return response()->json($equiv);
    }

    public function destroy($id)
    {
        EquivalenciaAlimento::destroy($id);
        return response()->json(['mensaje' => 'Equivalencia eliminada']);
    }

    public function buscar(Request $request)
    {
        $query = $request->query('nombre');
        $resultados = EquivalenciaAlimento::where('alimentos.nombre', 'like', "%$query%")->get();
        return response()->json($resultados);
    }

    /**
     * Busca el alimento y sus equivalentes en una sola petición a Edamam
     */
    public function buscarEquivalentes(Request $request)
    {
        $query = $request->query('q');
        if (empty($query)) {
            return response()->json(['mensaje' => 'Falta el parámetro q'], 400);
        }

        $appId  = env('EDAMAM_APP_ID');
        $appKey = env('EDAMAM_APP_KEY');

        // Si faltan credenciales, recurrir a la respuesta mock
        if (empty($appId) || empty($appKey)) {
            return $this->respuestaMock($query);
        }

        try {
            // Realizar una única llamada a Edamam (ultra rápido)
            $response = Http::get('https://api.edamam.com/api/food-database/v2/parser', [
                'app_id'  => $appId,
                'app_key' => $appKey,
                'ingr'    => $query,
            ]);

            if ($response->failed()) {
                return response()->json(['mensaje' => 'Error al consultar Edamam'], 500);
            }

            $hints = $response->json('hints', []);
            if (empty($hints)) {
                return response()->json(['mensaje' => 'No se encontraron resultados'], 404);
            }

            // Primer resultado -> Alimento base u original
            $foodOrig = $hints[0]['food'];
            $nutrOrig = $foodOrig['nutrients'] ?? [];

            $original = [
                'nombre'        => $foodOrig['label'] ?? $query,
                'grupo'         => $foodOrig['category'] ?? 'General',
                'calorias'      => round($nutrOrig['ENERC_KCAL'] ?? 0),
                'proteinas'     => round($nutrOrig['PROCNT'] ?? 0, 1),
                'carbohidratos' => round($nutrOrig['CHOCDF'] ?? 0, 1),
                'grasas'        => round($nutrOrig['FAT'] ?? 0, 1),
                'fibra'         => round($nutrOrig['FIBTG'] ?? 0, 1),
                'unidad'        => '100g',
                'imagen'        => $foodOrig['image'] ?? null
            ];

            // Del 2do al 5to resultado -> Alternativas equivalentes
            $equivalentes = collect(array_slice($hints, 1, 4))->map(function ($item) use ($original) {
                $food = $item['food'];
                $nutrients = $food['nutrients'] ?? [];
                $caloriasEq = round($nutrients['ENERC_KCAL'] ?? 0);

                return [
                    'nombre'        => $food['label'] ?? 'Equivalente',
                    'grupo'         => $food['category'] ?? 'Equivalente',
                    'calorias'      => $caloriasEq,
                    'proteinas'     => round($nutrients['PROCNT'] ?? 0, 1),
                    'carbohidratos' => round($nutrients['CHOCDF'] ?? 0, 1),
                    'grasas'        => round($nutrients['FAT'] ?? 0, 1),
                    'fibra'         => round($nutrients['FIBTG'] ?? 0, 1),
                    'unidad'        => '100g',
                    'diferencia'    => $caloriasEq - $original['calorias'],
                    'imagen'        => $food['image'] ?? null
                ];
            })->values();

            return response()->json([
                'original'     => $original,
                'equivalentes' => $equivalentes
            ]);

        } catch (\Exception $e) {
            return response()->json(['mensaje' => 'Error de conexión: ' . $e->getMessage()], 500);
        }
    }

    private function respuestaMock(string $query)
    {
        return response()->json([
            'original' => [
                'nombre' => ucfirst($query) . ' (aprox.)',
                'grupo' => 'General',
                'calorias' => 150,
                'proteinas' => 10.0,
                'carbohidratos' => 20.0,
                'grasas' => 3.0,
                'fibra' => 1.5,
                'unidad' => '100g'
            ],
            'equivalentes' => [],
            'es_mock' => true
        ]);
    }
}