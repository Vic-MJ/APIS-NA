<?php

namespace App\Http\Controllers;

use App\Models\Nutriologo;
use App\Models\Usuario;
use Illuminate\Http\Request;

class ControladorNutriologo extends Controller
{
    public function index()
    {
        // Obtenemos todos los usuarios que son nutriólogos
        $usuariosNutriologos = Usuario::where('rol', 'nutriologo')->get();

        // Los mapeamos al formato de respuesta esperado para profesionales
        $respuesta = $usuariosNutriologos->map(function($u) {
            return [
                'id' => $u->_id,
                'nombre' => $u->nombreCompleto,
                'especialidad' => $u->tipo_cedula ?? 'Nutriólogo General',
                'cedula' => $u->cedula ?? 'Sin cédula',
                'calificacion' => 5.0, // Mock data
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
