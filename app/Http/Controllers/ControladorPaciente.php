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
            // VINCULACIÓN BIDIRECCIONAL:
            // Buscamos al nutriólogo. Debido a la posible corrupción de datos, intentamos varias formas.

            // Intento 1: Búsqueda normal (si los datos están limpios)
            $perfilNutri = \App\Models\Nutriologo::where('usuario.id_usuario', $idNutriologo)->first();

            // Intento 2: Búsqueda manual si la colección está corrupta (usuario es string)
            if (!$perfilNutri) {
                $todos = \App\Models\Nutriologo::all();
                foreach ($todos as $n) {
                    $uData = $n->usuario;
                    if (is_string($uData)) {
                        $uData = json_decode($uData, true);
                    }
                    if (isset($uData['id_usuario']) && $uData['id_usuario'] == $idNutriologo) {
                        $perfilNutri = $n;
                        break;
                    }
                }
            }

            if ($perfilNutri) {
                // REPARACIÓN AL VUELO: Si detectamos que los campos son strings, los corregimos a arreglos reales
                $camposArray = ['usuario', 'nombre', 'pacientes'];
                foreach ($camposArray as $campo) {
                    $val = $perfilNutri->$campo;
                    while (is_string($val)) {
                        $decoded = json_decode($val, true);
                        if (json_last_error() !== JSON_ERROR_NONE) break;
                        $val = $decoded;
                    }
                    // Si no es un arreglo tras decodificar, forzamos uno vacío para pacientes
                    if (!is_array($val)) {
                        $val = ($campo === 'pacientes') ? [] : $val;
                    }
                    $perfilNutri->$campo = $val;
                }

                // Ahora añadimos al paciente asegurándonos de que sea un arreglo real
                $listaPacientes = is_array($perfilNutri->pacientes) ? $perfilNutri->pacientes : [];
                if (!in_array($idPaciente, $listaPacientes)) {
                    $listaPacientes[] = $idPaciente;
                    $perfilNutri->pacientes = $listaPacientes;
                    $perfilNutri->save();
                    \Log::info("Vinculación exitosa: Paciente $idPaciente añadido a Nutri $idNutriologo");
                }
            } else {
                \Log::error("No se encontró el perfil de nutriólogo para el ID: $idNutriologo");
            }

            return response()->json([
                'mensaje' => 'Vinculación establecida correctamente',
                'usuario' => $usuario->fresh(),
                'viculado_a' => $idNutriologo
            ]);
        }

        return response()->json(['mensaje' => 'Error al intentar guardar la vinculación'], 500);
    }

    public function desvincularNutriologo()
    {
        /** @var \App\Models\Usuario $usuario */
        $usuario = \Illuminate\Support\Facades\Auth::user();

        if (!$usuario) {
            return response()->json(['mensaje' => 'Usuario no encontrado'], 401);
        }

        $idNutriologo = $usuario->nutriologo_id;

        if ($idNutriologo) {
            // Buscamos al nutriólogo para quitar al paciente de su lista
            $perfilNutri = \App\Models\Nutriologo::where('usuario.id_usuario', $idNutriologo)
                ->orWhere('usuario.id_usuario', (string)$idNutriologo)
                ->first();

            if ($perfilNutri) {
                // Removemos el ID del paciente del arreglo 'pacientes'
                $perfilNutri->pull('pacientes', (string)$usuario->_id);
            }
        }

        // Limpiamos el vínculo en el usuario
        $usuario->update(['nutriologo_id' => null]);

        return response()->json([
            'mensaje' => 'Desvinculación exitosa',
            'usuario' => $usuario->fresh()
        ]);
    }
}
