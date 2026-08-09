<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use Illuminate\Http\Request;

class ControladorPaciente extends Controller
{
    public function index()
    {
        return response()->json(Paciente::all());
    }

    public function store(Request $request)
    {
        $paciente = Paciente::create($request->all());
        return response()->json($paciente, 201);
    }

    public function show($id)
    {
        return response()->json(Paciente::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $paciente = Paciente::findOrFail($id);
        $paciente->update($request->all());
        return response()->json($paciente);
    }

    public function destroy($id)
    {
        Paciente::destroy($id);
        return response()->json(['mensaje' => 'Paciente eliminado']);
    }

    public function buscar($idUsuario)
    {
        return response()->json(Paciente::where('usuario_cliente.id_usuario', (int)$idUsuario)->first());
    }

    public function vincularNutriologo(Request $request)
    {
        $request->validate([
            'nutriologo_id' => 'required|string'
        ]);

        $usuario = \App\Models\Usuario::find(\Illuminate\Support\Facades\Auth::id());
        if (!$usuario) {
            return response()->json(['mensaje' => 'Usuario no encontrado'], 404);
        }

        $usuario->nutriologo_id = $request->nutriologo_id;
        $usuario->save();

        return response()->json([
            'mensaje' => 'Vinculación exitosa',
            'usuario' => $usuario
        ]);
    }
}
