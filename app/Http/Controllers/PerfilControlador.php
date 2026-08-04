<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PerfilControlador extends Controller
{

    public function obtenerPerfil()
    {
        $usuario = Auth::user();
        return response()->json($usuario);
    }

    public function actualizarPerfil(Request $request)
    {
        $usuario = Auth::user();

        $request->validate([
            'nombres' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'nullable|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo,' . $usuario->_id . ',_id',
            'password' => 'nullable|string|min:8|confirmed',
            'cedula' => 'nullable|string',
            'tipo_cedula' => 'nullable|string',
        ]);

        $usuario->nombre = [
            'nombres' => $request->nombres,
            'apellido_p' => $request->apellido_p,
            'apellido_m' => $request->apellido_m,
        ];

        $usuario->correo = $request->correo;

        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }

        if ($usuario->rol === 'nutriologo') {
            $usuario->cedula = $request->cedula;
            $usuario->tipo_cedula = $request->tipo_cedula;
        }
        if ($usuario->rol === 'paciente') {
            $usuario->tipo_paciente = $request->tipo_paciente ?? 'free';
        }

        $usuario->save();

        return response()->json([
            'mensaje' => 'Perfil actualizado correctamente',
            'usuario' => $usuario
        ]);
    }

    public function actualizarIdentificacion(Request $request)
    {
        $usuario = Auth::user();

        $request->validate([
            'ine_frente' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'ine_reverso' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('ine_frente')) {
            if ($usuario->ine_frente) {
                Storage::disk('public')->delete($usuario->ine_frente);
            }
            $usuario->ine_frente = $request->file('ine_frente')->store('identificaciones', 'public');
        }

        if ($request->hasFile('ine_reverso')) {
            if ($usuario->ine_reverso) {
                Storage::disk('public')->delete($usuario->ine_reverso);
            }
            $usuario->ine_reverso = $request->file('ine_reverso')->store('identificaciones', 'public');
        }

        $usuario->save();

        return response()->json([
            'mensaje' => 'Identificación actualizada correctamente',
            'usuario' => $usuario
        ]);
    }
}
