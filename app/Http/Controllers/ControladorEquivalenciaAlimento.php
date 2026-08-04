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
     * Busca equivalentes nutricionales de un alimento usando la API de Edamam.
     * Retorna el alimento original con sus macros y una lista de equivalentes
     * del mismo grupo con calorías similares (±20%).
     */
    public function buscarEquivalentes(Request $request)
    {
        $query = $request->query('q');
        if (empty($query)) {
            return response()->json(['error' => 'Parámetro q requerido'], 400);
        }

        $appId  = env('EDAMAM_APP_ID');
        $appKey = env('EDAMAM_APP_KEY');

        // Mapa de grupos alimenticios con términos de búsqueda para equivalentes
        $terminosPorGrupo = [
            'Proteínas'     => ['chicken breast', 'turkey', 'tuna', 'salmon', 'eggs', 'beef', 'shrimp', 'cottage cheese', 'tofu', 'lentils'],
            'Carbohidratos' => ['rice', 'pasta', 'bread', 'oats', 'potato', 'quinoa', 'corn tortilla', 'barley', 'sweet potato'],
            'Frutas'        => ['apple', 'banana', 'orange', 'mango', 'strawberry', 'grapes', 'pear', 'peach', 'watermelon'],
            'Verduras'      => ['spinach', 'broccoli', 'carrot', 'zucchini', 'tomato', 'cucumber', 'lettuce', 'kale', 'green beans'],
            'Lácteos'       => ['milk', 'yogurt', 'cheese', 'greek yogurt', 'mozzarella', 'ricotta', 'kefir'],
            'Grasas'        => ['avocado', 'olive oil', 'almonds', 'walnuts', 'peanut butter', 'chia seeds', 'flaxseed', 'cashews'],
            'Otros'         => ['soup', 'salad', 'mixed dish', 'stew', 'sandwich'],
        ];

        // Si no hay credenciales, devolver datos mock enriquecidos
        if (empty($appId) || empty($appKey)) {
            return $this->respuestaMock($query);
        }

        try {
            // 1. Buscar el alimento original
            $respuestaOriginal = Http::get('https://api.edamam.com/api/food-database/v2/parser', [
                'app_id'  => $appId,
                'app_key' => $appKey,
                'ingr'    => $query,
            ]);

            if (!$respuestaOriginal->successful() || empty($respuestaOriginal->json()['hints'])) {
                return response()->json(['error' => 'No se encontró el alimento'], 404);
            }

            $datosOriginales = $respuestaOriginal->json()['hints'][0]['food'];
            $nutrientesOrig  = $datosOriginales['nutrients'] ?? [];

            $caloriasOrig  = round($nutrientesOrig['ENERC_KCAL'] ?? 0);
            $proteinasOrig = round($nutrientesOrig['PROCNT'] ?? 0, 1);
            $carbsOrig     = round($nutrientesOrig['CHOCDF'] ?? 0, 1);
            $grasasOrig    = round($nutrientesOrig['FAT'] ?? 0, 1);
            $fibraOrig     = round($nutrientesOrig['FIBTG'] ?? 0, 1);

            $grupoOrig = $this->detectarGrupo($datosOriginales);

            $alimentoOriginal = [
                'nombre'          => $datosOriginales['label'],
                'calorias'        => $caloriasOrig,
                'proteinas'       => $proteinasOrig,
                'carbohidratos'   => $carbsOrig,
                'grasas'          => $grasasOrig,
                'fibra'           => $fibraOrig,
                'grupo'           => $grupoOrig,
                'unidad'          => 'por 100g',
                'imagen'          => $datosOriginales['image'] ?? null,
            ];

            // 2. Buscar equivalentes del mismo grupo
            $terminosBusqueda = $terminosPorGrupo[$grupoOrig] ?? $terminosPorGrupo['Otros'];
            $equivalentes = [];
            $nombresVistos = [strtolower($datosOriginales['label'])];

            foreach ($terminosBusqueda as $termino) {
                if (count($equivalentes) >= 8) break;

                $respEq = Http::get('https://api.edamam.com/api/food-database/v2/parser', [
                    'app_id'  => $appId,
                    'app_key' => $appKey,
                    'ingr'    => $termino,
                ]);

                if (!$respEq->successful()) continue;
                $hintsEq = $respEq->json()['hints'] ?? [];

                foreach (array_slice($hintsEq, 0, 2) as $hint) {
                    $food = $hint['food'];
                    $nombre = strtolower($food['label']);

                    // Evitar duplicados y el mismo alimento original
                    if (in_array($nombre, $nombresVistos)) continue;
                    $nombresVistos[] = $nombre;

                    $nutrientes = $food['nutrients'] ?? [];
                    $calorias   = round($nutrientes['ENERC_KCAL'] ?? 0);

                    // Filtrar por rango calórico ±20%
                    if ($caloriasOrig > 0) {
                        $pct = abs($calorias - $caloriasOrig) / $caloriasOrig;
                        if ($pct > 0.20) continue;
                    }

                    $equivalentes[] = [
                        'nombre'        => $food['label'],
                        'calorias'      => $calorias,
                        'proteinas'     => round($nutrientes['PROCNT'] ?? 0, 1),
                        'carbohidratos' => round($nutrientes['CHOCDF'] ?? 0, 1),
                        'grasas'        => round($nutrientes['FAT'] ?? 0, 1),
                        'fibra'         => round($nutrientes['FIBTG'] ?? 0, 1),
                        'grupo'         => $this->detectarGrupo($food),
                        'diferencia'    => $calorias - $caloriasOrig,
                        'unidad'        => 'por 100g',
                        'imagen'        => $food['image'] ?? null,
                    ];

                    if (count($equivalentes) >= 8) break;
                }
            }

            return response()->json([
                'original'    => $alimentoOriginal,
                'equivalentes' => $equivalentes,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Detecta el grupo alimenticio basándose en los datos del alimento de Edamam.
     */
    private function detectarGrupo(array $food): string
    {
        $categoria = $food['category'] ?? '';
        $label     = strtolower($food['label'] ?? '');

        if (stripos($categoria, 'poultry') !== false || stripos($categoria, 'meat') !== false ||
            stripos($categoria, 'fish') !== false   || stripos($categoria, 'egg') !== false  ||
            stripos($label, 'chicken') !== false     || stripos($label, 'turkey') !== false   ||
            stripos($label, 'tuna') !== false        || stripos($label, 'salmon') !== false   ||
            stripos($label, 'beef') !== false        || stripos($label, 'pork') !== false     ||
            stripos($label, 'shrimp') !== false      || stripos($label, 'tofu') !== false) {
            return 'Proteínas';
        }
        if (stripos($categoria, 'grain') !== false  || stripos($categoria, 'bread') !== false ||
            stripos($categoria, 'pasta') !== false   || stripos($label, 'rice') !== false     ||
            stripos($label, 'potato') !== false      || stripos($label, 'oat') !== false      ||
            stripos($label, 'tortilla') !== false    || stripos($label, 'quinoa') !== false) {
            return 'Carbohidratos';
        }
        if (stripos($categoria, 'fruit') !== false  || stripos($label, 'apple') !== false    ||
            stripos($label, 'banana') !== false      || stripos($label, 'mango') !== false    ||
            stripos($label, 'orange') !== false      || stripos($label, 'berry') !== false) {
            return 'Frutas';
        }
        if (stripos($categoria, 'vegetable') !== false || stripos($label, 'spinach') !== false ||
            stripos($label, 'broccoli') !== false   || stripos($label, 'carrot') !== false   ||
            stripos($label, 'lettuce') !== false     || stripos($label, 'kale') !== false) {
            return 'Verduras';
        }
        if (stripos($categoria, 'dairy') !== false  || stripos($categoria, 'milk') !== false ||
            stripos($label, 'yogurt') !== false      || stripos($label, 'cheese') !== false  ||
            stripos($label, 'kefir') !== false) {
            return 'Lácteos';
        }
        if (stripos($categoria, 'oil') !== false    || stripos($categoria, 'fat') !== false  ||
            stripos($categoria, 'nut') !== false     || stripos($label, 'avocado') !== false  ||
            stripos($label, 'almond') !== false      || stripos($label, 'walnut') !== false   ||
            stripos($label, 'peanut') !== false      || stripos($label, 'chia') !== false) {
            return 'Grasas';
        }

        return 'Otros';
    }

    /**
     * Datos mock enriquecidos cuando no hay credenciales de Edamam.
     */
    private function respuestaMock(string $query): \Illuminate\Http\JsonResponse
    {
        $queryLow = mb_strtolower(trim($query));

        $grupos = [
            'Proteínas' => [
                ['nombre' => 'Pechuga de pollo', 'calorias' => 165, 'proteinas' => 31.0, 'carbohidratos' => 0.0, 'grasas' => 3.6, 'fibra' => 0.0],
                ['nombre' => 'Pavo (pechuga)', 'calorias' => 157, 'proteinas' => 29.9, 'carbohidratos' => 0.0, 'grasas' => 3.2, 'fibra' => 0.0],
                ['nombre' => 'Atún en agua', 'calorias' => 108, 'proteinas' => 23.6, 'carbohidratos' => 0.0, 'grasas' => 0.8, 'fibra' => 0.0],
                ['nombre' => 'Claras de huevo', 'calorias' => 52, 'proteinas' => 10.9, 'carbohidratos' => 0.7, 'grasas' => 0.2, 'fibra' => 0.0],
                ['nombre' => 'Salmón', 'calorias' => 208, 'proteinas' => 20.4, 'carbohidratos' => 0.0, 'grasas' => 13.4, 'fibra' => 0.0],
                ['nombre' => 'Tofu firme', 'calorias' => 76, 'proteinas' => 8.1, 'carbohidratos' => 1.9, 'grasas' => 4.8, 'fibra' => 0.3],
                ['nombre' => 'Camarones cocidos', 'calorias' => 99, 'proteinas' => 21.0, 'carbohidratos' => 0.0, 'grasas' => 1.1, 'fibra' => 0.0],
                ['nombre' => 'Res (bistec magro)', 'calorias' => 158, 'proteinas' => 26.1, 'carbohidratos' => 0.0, 'grasas' => 5.4, 'fibra' => 0.0],
            ],
            'Carbohidratos' => [
                ['nombre' => 'Arroz blanco cocido', 'calorias' => 130, 'proteinas' => 2.7, 'carbohidratos' => 28.2, 'grasas' => 0.3, 'fibra' => 0.4],
                ['nombre' => 'Pasta integral cocida', 'calorias' => 124, 'proteinas' => 5.3, 'carbohidratos' => 26.5, 'grasas' => 0.5, 'fibra' => 2.5],
                ['nombre' => 'Avena cocida', 'calorias' => 71, 'proteinas' => 2.5, 'carbohidratos' => 12.0, 'grasas' => 1.5, 'fibra' => 1.7],
                ['nombre' => 'Camote cocido', 'calorias' => 86, 'proteinas' => 1.6, 'carbohidratos' => 20.1, 'grasas' => 0.1, 'fibra' => 3.0],
                ['nombre' => 'Quinoa cocida', 'calorias' => 120, 'proteinas' => 4.4, 'carbohidratos' => 21.3, 'grasas' => 1.9, 'fibra' => 2.8],
                ['nombre' => 'Papa cocida', 'calorias' => 87, 'proteinas' => 1.9, 'carbohidratos' => 20.1, 'grasas' => 0.1, 'fibra' => 1.8],
                ['nombre' => 'Tortilla de maíz', 'calorias' => 218, 'proteinas' => 5.7, 'carbohidratos' => 46.0, 'grasas' => 2.6, 'fibra' => 5.2],
                ['nombre' => 'Pan integral', 'calorias' => 247, 'proteinas' => 12.3, 'carbohidratos' => 41.0, 'grasas' => 4.2, 'fibra' => 6.0],
            ],
            'Frutas' => [
                ['nombre' => 'Manzana', 'calorias' => 52, 'proteinas' => 0.3, 'carbohidratos' => 13.8, 'grasas' => 0.2, 'fibra' => 2.4],
                ['nombre' => 'Plátano', 'calorias' => 89, 'proteinas' => 1.1, 'carbohidratos' => 22.8, 'grasas' => 0.3, 'fibra' => 2.6],
                ['nombre' => 'Naranja', 'calorias' => 47, 'proteinas' => 0.9, 'carbohidratos' => 11.8, 'grasas' => 0.1, 'fibra' => 2.4],
                ['nombre' => 'Mango', 'calorias' => 60, 'proteinas' => 0.8, 'carbohidratos' => 15.0, 'grasas' => 0.4, 'fibra' => 1.6],
                ['nombre' => 'Fresas', 'calorias' => 32, 'proteinas' => 0.7, 'carbohidratos' => 7.7, 'grasas' => 0.3, 'fibra' => 2.0],
                ['nombre' => 'Uvas', 'calorias' => 69, 'proteinas' => 0.6, 'carbohidratos' => 18.1, 'grasas' => 0.2, 'fibra' => 0.9],
                ['nombre' => 'Pera', 'calorias' => 57, 'proteinas' => 0.4, 'carbohidratos' => 15.2, 'grasas' => 0.1, 'fibra' => 3.1],
                ['nombre' => 'Durazno', 'calorias' => 39, 'proteinas' => 0.9, 'carbohidratos' => 9.5, 'grasas' => 0.3, 'fibra' => 1.5],
            ],
            'Verduras' => [
                ['nombre' => 'Espinaca', 'calorias' => 23, 'proteinas' => 2.9, 'carbohidratos' => 3.6, 'grasas' => 0.4, 'fibra' => 2.2],
                ['nombre' => 'Brócoli', 'calorias' => 34, 'proteinas' => 2.8, 'carbohidratos' => 6.6, 'grasas' => 0.4, 'fibra' => 2.6],
                ['nombre' => 'Zanahoria', 'calorias' => 41, 'proteinas' => 0.9, 'carbohidratos' => 9.6, 'grasas' => 0.2, 'fibra' => 2.8],
                ['nombre' => 'Calabacín', 'calorias' => 17, 'proteinas' => 1.2, 'carbohidratos' => 3.1, 'grasas' => 0.3, 'fibra' => 1.0],
                ['nombre' => 'Tomate', 'calorias' => 18, 'proteinas' => 0.9, 'carbohidratos' => 3.9, 'grasas' => 0.2, 'fibra' => 1.2],
                ['nombre' => 'Pepino', 'calorias' => 15, 'proteinas' => 0.7, 'carbohidratos' => 3.6, 'grasas' => 0.1, 'fibra' => 0.5],
                ['nombre' => 'Lechuga romana', 'calorias' => 17, 'proteinas' => 1.2, 'carbohidratos' => 3.3, 'grasas' => 0.3, 'fibra' => 2.1],
                ['nombre' => 'Kale', 'calorias' => 49, 'proteinas' => 4.3, 'carbohidratos' => 8.8, 'grasas' => 0.9, 'fibra' => 3.6],
            ],
            'Lácteos' => [
                ['nombre' => 'Leche entera', 'calorias' => 61, 'proteinas' => 3.2, 'carbohidratos' => 4.8, 'grasas' => 3.3, 'fibra' => 0.0],
                ['nombre' => 'Yogurt griego natural', 'calorias' => 59, 'proteinas' => 10.2, 'carbohidratos' => 3.6, 'grasas' => 0.4, 'fibra' => 0.0],
                ['nombre' => 'Queso cottage', 'calorias' => 98, 'proteinas' => 11.1, 'carbohidratos' => 3.4, 'grasas' => 4.3, 'fibra' => 0.0],
                ['nombre' => 'Queso mozzarella', 'calorias' => 280, 'proteinas' => 28.1, 'carbohidratos' => 3.1, 'grasas' => 17.1, 'fibra' => 0.0],
                ['nombre' => 'Kéfir', 'calorias' => 61, 'proteinas' => 3.4, 'carbohidratos' => 4.5, 'grasas' => 3.5, 'fibra' => 0.0],
                ['nombre' => 'Queso panela', 'calorias' => 103, 'proteinas' => 17.9, 'carbohidratos' => 0.5, 'grasas' => 3.1, 'fibra' => 0.0],
            ],
            'Grasas' => [
                ['nombre' => 'Aguacate', 'calorias' => 160, 'proteinas' => 2.0, 'carbohidratos' => 8.5, 'grasas' => 14.7, 'fibra' => 6.7],
                ['nombre' => 'Almendras', 'calorias' => 579, 'proteinas' => 21.2, 'carbohidratos' => 21.7, 'grasas' => 49.9, 'fibra' => 12.5],
                ['nombre' => 'Nueces', 'calorias' => 654, 'proteinas' => 15.2, 'carbohidratos' => 13.7, 'grasas' => 65.2, 'fibra' => 6.7],
                ['nombre' => 'Mantequilla de cacahuate', 'calorias' => 588, 'proteinas' => 25.1, 'carbohidratos' => 20.1, 'grasas' => 50.4, 'fibra' => 6.0],
                ['nombre' => 'Semillas de chía', 'calorias' => 486, 'proteinas' => 16.5, 'carbohidratos' => 42.1, 'grasas' => 30.7, 'fibra' => 34.4],
                ['nombre' => 'Aceite de oliva', 'calorias' => 884, 'proteinas' => 0.0, 'carbohidratos' => 0.0, 'grasas' => 100.0, 'fibra' => 0.0],
                ['nombre' => 'Semillas de linaza', 'calorias' => 534, 'proteinas' => 18.3, 'carbohidratos' => 28.9, 'grasas' => 42.2, 'fibra' => 27.3],
                ['nombre' => 'Anacardos (cashews)', 'calorias' => 553, 'proteinas' => 18.2, 'carbohidratos' => 30.2, 'grasas' => 43.8, 'fibra' => 3.3],
            ],
        ];

        // Intentar hacer match del query con algún alimento del mock
        $grupoDetectado = 'Proteínas';
        $alimentoOriginalMock = null;
        $coincidenciaMax = 0;

        foreach ($grupos as $grupo => $alimentos) {
            foreach ($alimentos as $alimento) {
                $similitud = similar_text(
                    $queryLow,
                    mb_strtolower($alimento['nombre']),
                    $porcentaje
                );
                if ($porcentaje > $coincidenciaMax) {
                    $coincidenciaMax = $porcentaje;
                    $grupoDetectado = $grupo;
                    $alimentoOriginalMock = $alimento;
                }
            }
        }

        if (!$alimentoOriginalMock) {
            $alimentoOriginalMock = $grupos['Proteínas'][0];
            $grupoDetectado = 'Proteínas';
        }

        $original = array_merge($alimentoOriginalMock, [
            'nombre' => ucwords($query) . ' (aprox.)',
            'grupo'  => $grupoDetectado,
            'unidad' => 'por 100g',
            'imagen' => null,
        ]);

        // Equivalentes: otros del mismo grupo con diferencia calórica calculada
        $equivalentesMock = array_values(array_filter(
            $grupos[$grupoDetectado],
            fn($a) => mb_strtolower($a['nombre']) !== mb_strtolower($alimentoOriginalMock['nombre'])
        ));

        $equivalentesMock = array_map(fn($a) => array_merge($a, [
            'grupo'      => $grupoDetectado,
            'diferencia' => $a['calorias'] - $original['calorias'],
            'unidad'     => 'por 100g',
            'imagen'     => null,
        ]), array_slice($equivalentesMock, 0, 7));

        return response()->json([
            'original'     => $original,
            'equivalentes' => $equivalentesMock,
            'es_mock'      => true,
        ]);
    }
}
