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
            return response()->json(['mensaje' => 'Usuario no encontrado en sesión'], 401);
        }

        $idPaciente = (string)$usuario->_id;
        $idNutriologo = (string)$request->nutriologo_id;

        // 1. Actualización del Paciente
        $actualizado = $usuario->update([
            'nutriologo_id' => $idNutriologo
        ]);

        if ($actualizado) {
            // 2. VINCULACIÓN BIDIRECCIONAL:
            // Buscamos al nutriólogo en la colección 'nutriologos'
            $perfilNutri = \App\Models\Nutriologo::where('usuario.id_usuario', $idNutriologo)
                ->orWhere('usuario.id_usuario', (string)$idNutriologo)
                ->first();

            if ($perfilNutri) {
                // SANITIZACIÓN: Aseguramos que sea arreglo nativo
                $pacientesActuales = $perfilNutri->pacientes;
                if (is_string($pacientesActuales)) {
                    $decoded = json_decode($pacientesActuales, true);
                    $perfilNutri->pacientes = is_array($decoded) ? $decoded : [];
                }

                // Añadimos al paciente al arreglo sin duplicados
                $perfilNutri->push('pacientes', $idPaciente, true);

                // Forzamos el guardado del perfil del nutriólogo
                $perfilNutri->save();
            }

            return response()->json([
                'mensaje' => 'Vinculación establecida correctamente',
                'usuario' => $usuario->fresh(),
                'viculado_a' => $idNutriologo
            ]);
        }

        return response()->json(['mensaje' => 'Error al intentar guardar la vinculación'], 500);
    }
}
