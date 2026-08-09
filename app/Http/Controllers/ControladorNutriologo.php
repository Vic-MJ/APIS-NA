<?php

namespace App\Http\Controllers;

use App\Models\Nutriologo;
use App\Models\Usuario;
use Illuminate\Http\Request;

class ControladorNutriologo extends Controller
{
    public function index()
    {
        // Obtenemos todos los usuarios que tienen rol de nutriologo (insensible a mayúsculas)
        $usuariosNutriologos = Usuario::where('rol', 'regexp', '/nutriologo/i')->get();

        // Los mapeamos al formato de respuesta esperado para profesionales
        $respuesta = $usuariosNutriologos->map(function($u) {
            // Aseguramos que el nombre nunca sea nulo
            $nombreAMostrar = $u->nombre_completo;
            if (empty($nombreAMostrar)) {
                 if (is_array($u->nombre)) {
                     $nombreAMostrar = trim(($u->nombre['nombres'] ?? '') . ' ' . ($u->nombre['apellido_p'] ?? ''));
                 }
            }

            // Fallback final: Correo o nombre genérico
            if (empty($nombreAMostrar)) {
                $nombreAMostrar = $u->correo ?? "Especialista Nutricional";
            }

            return [
                'id' => (string)$u->_id,
                'nombre' => (string)$nombreAMostrar,
                'especialidad' => (string)($u->tipo_cedula ?? 'Nutriólogo General'),
                'cedula' => (string)($u->cedula ?? 'Sin cédula'),
                'calificacion' => 5.0,
                'resenas' => 0,
                'imagen' => null
            ];
        });

        return response()->json($respuesta);
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
