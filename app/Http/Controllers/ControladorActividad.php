<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Http\Request;

class ControladorActividad extends Controller
{
    /**
     * Obtener todas las actividades (ordenadas por fecha de creación)
     */
    public function index()
    {
        // Se devuelven las más recientes primero
        return response()->json(Actividad::orderBy('created_at', 'desc')->get());
    }

    /**
     * Guardar una nueva actividad física aceptando múltiples formatos de payload desde Android
     */
    public function store(Request $request)
    {
        // Soporte flexible para los nombres de campos que envía la App Android
        $tipo = $request->input('tipo') ?? $request->input('nombre') ?? 'Cardio';
        $duracionMin = $request->input('duracion_min') ?? $request->input('duracion') ?? 30;
        $calorias = $request->input('calorias_quemadas') ?? $request->input('calorias') ?? 0;
        $intensidad = $request->input('intensidad') ?? 'Media';

        $actividad = Actividad::create([
            'tipo'              => $tipo,
            'nombre'            => $tipo,
            'duracion_min'      => is_numeric($duracionMin) ? (int)$duracionMin : 30,
            'duracion'          => is_numeric($duracionMin) ? "{$duracionMin} min" : (string)$duracionMin,
            'calorias_quemadas' => (float)$calorias,
            'calorias'          => (float)$calorias,
            'intensidad'        => $intensidad,
            'hora'              => now()->toTimeString(),
            'id_usuario'        => auth()->id() ?? $request->input('usuario_id')
        ]);

        return response()->json($actividad, 201);
    }

    public function show($id)
    {
        return response()->json(Actividad::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $actividad = Actividad::findOrFail($id);
        $actividad->update($request->all());
        return response()->json($actividad);
    }

    public function destroy($id)
    {
        Actividad::destroy($id);
        return response()->json(['mensaje' => 'Actividad eliminada']);
    }

    public function porUsuario($usuarioId)
    {
        $actividades = Actividad::where('id_usuario', $usuarioId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($actividades);
    }
}