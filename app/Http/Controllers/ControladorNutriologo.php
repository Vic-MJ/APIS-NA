<?php

namespace App\Http\Controllers;

use App\Models\Nutriologo;
use Illuminate\Http\Request;

class ControladorNutriologo extends Controller
{
    public function index()
    {
        return response()->json(Nutriologo::all());
    }

    public function store(Request $request)
    {
        $nutriologo = Nutriologo::create($request->all());
        return response()->json($nutriologo, 201);
    }

    public function show($id)
    {
        return response()->json(Nutriologo::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $nutriologo = Nutriologo::findOrFail($id);
        $nutriologo->update($request->all());
        return response()->json($nutriologo);
    }

    public function destroy($id)
    {
        Nutriologo::destroy($id);
        return response()->json(['mensaje' => 'Nutriólogo eliminado']);
    }

    public function porEspecialidad($especialidad)
    {
        return response()->json(Nutriologo::where('especialidad', $especialidad)->get());
    }
}
