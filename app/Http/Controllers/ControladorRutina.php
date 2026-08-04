<?php

namespace App\Http\Controllers;

use App\Models\Rutina;
use Illuminate\Http\Request;

class ControladorRutina extends Controller
{
    public function index()
    {
        return response()->json(Rutina::all());
    }

    public function store(Request $request)
    {
        $rutina = Rutina::create($request->all());
        return response()->json($rutina, 201);
    }

    public function show($id)
    {
        return response()->json(Rutina::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $rutina = Rutina::findOrFail($id);
        $rutina->update($request->all());
        return response()->json($rutina);
    }

    public function destroy($id)
    {
        Rutina::destroy($id);
        return response()->json(['mensaje' => 'Rutina eliminada']);
    }

    public function porNivel($nivel)
    {
        $rutinas = Rutina::where('nivel_dificultad', $nivel)->get();
        return response()->json($rutinas);
    }
}
