<?php

namespace App\Http\Controllers;

use App\Models\Comida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ControladorComida extends Controller
{

    public function index()
    {
        // Solo devolvemos las comidas que pertenecen al usuario autenticado
        return response()->json(
            Comida::where('usuario_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $idDieta = (string)$request->id_dieta;
        $dia = $request->dia;
        $usuarioId = (string)Auth::id(); // Forzamos ID como string para evitar fallos de tipo

        if (!$usuarioId) {
            return response()->json(['mensaje' => 'No autenticado'], 401);
        }

        // Buscamos si ya existe un registro para este día y dieta de ESTE usuario
        $comida = Comida::where('usuario_id', $usuarioId)
                        ->where('dia', $dia)
                        ->first();

        if (!$comida) {
            $comida = new Comida();
            $comida->usuario_id = $usuarioId;
            $comida->id_dieta = $idDieta;
            $comida->dia = $dia;
        }

        // Sanitización de momentos: Siempre Array, nunca String
        $momentos = $request->momentos;
        if (is_string($momentos)) {
            $decoded = json_decode($momentos, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $momentos = $decoded;
            }
        }

        $comida->unset('momentos'); // Limpiamos para evitar mezclas en Mongo
        $comida->momentos = $momentos;

        if ($comida->save()) {
            return response()->json([
                'mensaje' => 'Comida guardada exitosamente en BD',
                'datos' => $comida
            ], 201);
        }

        return response()->json(['mensaje' => 'Error crítico al escribir en la base de datos'], 500);
    }

    public function show($id)
    {
        return response()->json(Comida::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        // Solo permitimos editar si el registro pertenece al usuario autenticado
        $comida = Comida::where('_id', $id)
                        ->where('usuario_id', Auth::id())
                        ->first();

        if (!$comida) {
            // Si no lo encuentra por ID, intentamos por Dieta y Dia (Robustez)
            $comida = Comida::where('usuario_id', Auth::id())
                            ->where('id_dieta', (string)$request->id_dieta)
                            ->where('dia', $request->dia)
                            ->firstOrFail();
        }

        if ($request->has('id_dieta')) {
            $comida->id_dieta = (string)$request->id_dieta;
        }
        if ($request->has('dia')) {
            $comida->dia = $request->dia;
        }
        if ($request->has('momentos')) {
            $momentos = $request->momentos;
            if (is_string($momentos)) {
                $decoded = json_decode($momentos, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $momentos = $decoded;
                }
            }
            $comida->unset('momentos');
            $comida->momentos = $momentos;
        }

        $comida->save();

        return response()->json($comida);
    }

    public function destroy($id)
    {
        Comida::destroy($id);
        return response()->json(['mensaje' => 'Comida eliminada']);
    }

    public function porDietaYDia($dietaId, $dia)
    {
        // Solo devolvemos si pertenece al usuario autenticado
        $comidas = Comida::where('usuario_id', Auth::id())
            ->where(function($query) use ($dietaId) {
                $query->where('id_dieta', $dietaId)
                      ->orWhere('id_dieta', (int)$dietaId)
                      ->orWhere('id_dieta', (string)$dietaId);
            })
            ->where('dia', $dia)
            ->get();

        return response()->json($comidas);
    }

    public function buscarAlimento(Request $request)
    {
        $query = $request->query('q');
        if (empty($query)) {
            return response()->json([], 400);
        }

        $appId = env('EDAMAM_APP_ID');
        $appKey = env('EDAMAM_APP_KEY');

        // Si no están configuradas las credenciales de Edamam, usar datos de simulación (mock data)
        if (empty($appId) || empty($appKey)) {
            $mockFoods = [
                'ensalada cesar' => [
                    'alimento' => 'Ensalada César',
                    'calorias' => 350,
                    'grupo_alimenticio' => 'Verduras',
                    'unidad' => 'porción',
                    'cantidad' => 1
                ],
                'tacos de pollo' => [
                    'alimento' => 'Tacos de pollo (3 piezas)',
                    'calorias' => 450,
                    'grupo_alimenticio' => 'Proteínas',
                    'unidad' => 'pieza',
                    'cantidad' => 3
                ],
                'manzana' => [
                    'alimento' => 'Manzana roja',
                    'calorias' => 95,
                    'grupo_alimenticio' => 'Frutas',
                    'unidad' => 'pieza',
                    'cantidad' => 1
                ],
                'avena' => [
                    'alimento' => 'Avena cocida',
                    'calorias' => 150,
                    'grupo_alimenticio' => 'Carbohidratos',
                    'unidad' => 'taza',
                    'cantidad' => 1
                ],
                'huevo' => [
                    'alimento' => 'Huevo entero',
                    'calorias' => 70,
                    'grupo_alimenticio' => 'Proteínas',
                    'unidad' => 'pieza',
                    'cantidad' => 1
                ],
                'platano' => [
                    'alimento' => 'Plátano maduro',
                    'calorias' => 105,
                    'grupo_alimenticio' => 'Frutas',
                    'unidad' => 'pieza',
                    'cantidad' => 1
                ],
                'arroz' => [
                    'alimento' => 'Arroz blanco cocido',
                    'calorias' => 200,
                    'grupo_alimenticio' => 'Carbohidratos',
                    'unidad' => 'taza',
                    'cantidad' => 1
                ],
                'pechuga de pollo' => [
                    'alimento' => 'Pechuga de pollo asada',
                    'calorias' => 165,
                    'grupo_alimenticio' => 'Proteínas',
                    'unidad' => 'gramos',
                    'cantidad' => 100
                ],
                'leche' => [
                    'alimento' => 'Leche entera',
                    'calorias' => 150,
                    'grupo_alimenticio' => 'Lácteos',
                    'unidad' => 'taza',
                    'cantidad' => 1
                ],
                'aguacate' => [
                    'alimento' => 'Aguacate',
                    'calorias' => 160,
                    'grupo_alimenticio' => 'Grasas',
                    'unidad' => 'pieza',
                    'cantidad' => 1
                ]
            ];

            $resultados = [];
            $queryLower = mb_strtolower(trim($query));

            foreach ($mockFoods as $key => $food) {
                if (strpos($key, $queryLower) !== false || strpos(mb_strtolower($food['alimento']), $queryLower) !== false) {
                    $resultados[] = $food;
                }
            }

            if (empty($resultados)) {
                $resultados[] = [
                    'alimento' => ucwords($query),
                    'calorias' => 120,
                    'grupo_alimenticio' => 'Otros',
                    'unidad' => 'porción',
                    'cantidad' => 1
                ];
            }

            return response()->json($resultados);
        }

        try {
            $response = Http::get('https://api.edamam.com/api/food-database/v2/parser', [
                'app_id' => $appId,
                'app_key' => $appKey,
                'ingr' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $resultados = [];

                if (isset($data['hints'])) {
                    foreach (array_slice($data['hints'], 0, 5) as $hint) {
                        $food = $hint['food'];

                        $categoria = $food['category'] ?? 'Generic foods';
                        $grupo = 'Otros';
                        if (stripos($categoria, 'poultry') !== false || stripos($categoria, 'meat') !== false || stripos($categoria, 'fish') !== false || stripos($categoria, 'egg') !== false) {
                            $grupo = 'Proteínas';
                        } elseif (stripos($categoria, 'grain') !== false || stripos($categoria, 'bread') !== false || stripos($categoria, 'pasta') !== false) {
                            $grupo = 'Carbohidratos';
                        } elseif (stripos($categoria, 'fruit') !== false) {
                            $grupo = 'Frutas';
                        } elseif (stripos($categoria, 'veg') !== false) {
                            $grupo = 'Verduras';
                        } elseif (stripos($categoria, 'dairy') !== false || stripos($categoria, 'milk') !== false) {
                            $grupo = 'Lácteos';
                        } elseif (stripos($categoria, 'oil') !== false || stripos($categoria, 'fat') !== false || stripos($categoria, 'nut') !== false) {
                            $grupo = 'Grasas';
                        }

                        $calorias = round($food['nutrients']['ENERC_KCAL'] ?? 0);

                        $resultados[] = [
                            'alimento' => $food['label'],
                            'calorias' => $calorias,
                            'grupo_alimenticio' => $grupo,
                            'unidad' => 'gramos',
                            'cantidad' => 100
                        ];
                    }
                }

                return response()->json($resultados);
            }

            return response()->json(['error' => 'Error al consultar Edamam'], $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
