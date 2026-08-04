<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use Illuminate\Http\Request;

class ControladorConsulta extends Controller
{
    public function index()
    {
        return response()->json(Consulta::all());
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'usuario_cliente' => 'required|array',
            'nutriologo' => 'required|array',
            'fecha_hora' => 'required|date',
            'tipo' => 'required|string',
            'estatus' => 'required|string',
        ]);

        $consulta = Consulta::create($request->all());
        return response()->json($consulta, 201);
    }

    public function show($id)
    {
        $consulta = Consulta::findOrFail($id);
        return response()->json($consulta);
    }

    public function update(Request $request, $id)
    {
        $consulta = Consulta::findOrFail($id);
        $consulta->update($request->all());
        return response()->json($consulta);
    }

    public function destroy($id)
    {
        Consulta::destroy($id);
        return response()->json(['mensaje' => 'Consulta eliminada']);
    }

    public function porUsuario($usuarioId)
    {
        $consultas = Consulta::where('usuario_cliente.id_usuario', (int)$usuarioId)->get();
        return response()->json($consultas);
    }
}
