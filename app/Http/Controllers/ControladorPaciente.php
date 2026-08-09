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

        /** @var \App\Models\Usuario $usuario */
        $usuario = \Illuminate\Support\Facades\Auth::user();

        if (!$usuario) {
            return response()->json(['mensaje' => 'Usuario no encontrado'], 404);
        }

        // Actualizamos el campo nutriologo_id del usuario actual
        $actualizado = $usuario->update([
            'nutriologo_id' => $request->nutriologo_id
        ]);

        if ($actualizado) {
            // VINCULACIÓN BIDIRECCIONAL:
            // Añadimos el ID del paciente a la lista de pacientes del nutriólogo
            $nutriologo = \App\Models\Nutriologo::where('usuario.id_usuario', $request->nutriologo_id)->first();

            if ($nutriologo) {
                // SANITIZACIÓN AL VUELO: Si el campo 'pacientes' era un string por error del pasado, lo convertimos a arreglo real
                if (is_string($nutriologo->pacientes)) {
                    $decoded = json_decode($nutriologo->pacientes, true);
                    $nutriologo->pacientes = is_array($decoded) ? $decoded : [];
                    $nutriologo->save();
                }

                // Usamos push para añadir al arreglo 'pacientes' sin duplicados (true)
                $nutriologo->push('pacientes', (string)$usuario->_id, true);
            }

            return response()->json([
                'mensaje' => 'Vinculación exitosa',
                'usuario' => $usuario->fresh()
            ]);
        }

        return response()->json(['mensaje' => 'No se pudo actualizar el registro'], 500);
    }
}
