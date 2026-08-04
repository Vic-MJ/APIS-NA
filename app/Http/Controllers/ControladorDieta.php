<?php

namespace App\Http\Controllers;

use App\Models\Dieta;
use Illuminate\Http\Request;

class ControladorDieta extends Controller
{
    public function index()
    {
        return response()->json(Dieta::all());
    }

    public function store(Request $request)
    {
        $dieta = Dieta::create($request->all());
        return response()->json($dieta, 201);
    }

    public function show($id)
    {
        return response()->json(Dieta::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $dieta = Dieta::findOrFail($id);
        $dieta->update($request->all());
        return response()->json($dieta);
    }

    public function destroy($id)
    {
        Dieta::destroy($id);
        return response()->json(['mensaje' => 'Dieta eliminada']);
    }

    public function activas(Request $request)
    {
        $usuarioId = $request->user()->id;

        $dietas = Dieta::where('usuario_cliente.id_usuario', (int)$usuarioId)
            ->where('estado', 'activa')
            ->get();
        return response()->json($dietas);
    }
}
