<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Http\Request;

class ControladorActividad extends Controller
{
    public function index()
    {
        return response()->json(Actividad::where('fecha', now()->startOfDay())->get());
    }

    public function store(Request $request)
    {
        $actividad = Actividad::create($request->all());
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
        $actividades = Actividad::where('id_usuario', $usuarioId)->get();
        return response()->json($actividades);
    }
}
