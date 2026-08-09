<?php

namespace App\Http\Controllers;

use App\Models\Nutriologo;
use App\Models\Usuario;
use Illuminate\Http\Request;

class ControladorNutriologo extends Controller
{
    public function index()
    {
        // Obtenemos a los profesionales directamente de su colección (nutriologos)
        $nutriologos = Nutriologo::all();

        // Los mapeamos al formato de respuesta esperado para profesionales
        $respuesta = $nutriologos->map(function($n) {
            $nombreArray = $n->nombre;
            $nombreStr = "Especialista";

            if (is_array($nombreArray)) {
                $nombreStr = trim(($nombreArray['nombres'] ?? '') . ' ' . ($nombreArray['apellido_p'] ?? ''));
            }

            return [
                'id' => (string)$n->_id,
                'nombre' => $nombreStr,
                'especialidad' => (string)($n->especialidad ?? 'Nutriólogo General'),
                'cedula' => (string)($n->cedula_profesional ?? 'Sin cédula'),
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
