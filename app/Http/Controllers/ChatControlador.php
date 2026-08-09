<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\ValidationException;

class ChatControlador extends Controller
{
    public function obtenerConversaciones()
    {
        $usuarioId = Auth::id();
        $emisores = Mensaje::where('receptor_id', $usuarioId)->pluck('emisor_id')->unique()->toArray();
        $receptores = Mensaje::where('emisor_id', $usuarioId)->pluck('receptor_id')->unique()->toArray();

        $usuarioIds = array_unique(array_merge($emisores, $receptores));

        $usuarios = Usuario::whereIn('_id', $usuarioIds)
            ->get(['_id', 'nombre', 'correo', 'rol']);

        $conversaciones = $usuarios->map(function ($usuario) use ($usuarioId) {
            $ultimoMensaje = Mensaje::where(function ($q) use ($usuarioId, $usuario) {
                $q->where('emisor_id', $usuarioId)->where('receptor_id', $usuario->_id);
            })->orWhere(function ($q) use ($usuarioId, $usuario) {
                $q->where('emisor_id', $usuario->_id)->where('receptor_id', $usuarioId);
            })->orderBy('created_at', 'desc')->first();

            $noLeidos = Mensaje::where('emisor_id', $usuario->_id)
                ->where('receptor_id', $usuarioId)
                ->where('leido', false)
                ->count();

            return [
                'usuario' => $usuario,
                'ultimo_mensaje' => $ultimoMensaje,
                'no_leidos' => $noLeidos
            ];
        });

        return response()->json($conversaciones->sortByDesc(function ($c) {
            return $c['ultimo_mensaje'] ? $c['ultimo_mensaje']->created_at : 0;
        })->values());
    }

    public function obtenerMensajes($otroUsuarioId)
    {
        $usuarioId = Auth::id();

        $mensajes = Mensaje::where(function ($q) use ($usuarioId, $otroUsuarioId) {
            $q->where('emisor_id', $usuarioId)->where('receptor_id', $otroUsuarioId);
        })->orWhere(function ($q) use ($usuarioId, $otroUsuarioId) {
            $q->where('emisor_id', $otroUsuarioId)->where('receptor_id', $usuarioId);
        })->orderBy('created_at', 'asc')->get();

        Mensaje::where('emisor_id', $otroUsuarioId)
            ->where('receptor_id', $usuarioId)
            ->where('leido', false)
            ->update(['leido' => true]);

        return response()->json($mensajes);
    }

    public function enviarMensaje(Request $request)
    {
        try {
            $request->validate([
                'receptor_id' => 'required|string',
                'contenido' => 'required|string',
            ]);

            $receptor = Usuario::where('_id', $request->receptor_id)->first();
            if (!$receptor) {
                return response()->json(['mensaje' => 'El receptor no existe: ' . $request->receptor_id], 404);
            }

            $usuario = Auth::user();

            $mensaje = Mensaje::create([
                'emisor_id' => $usuario->_id,
                'receptor_id' => $request->receptor_id,
                'contenido' => $request->contenido,
                'leido' => false,
                'fecha_envio' => now(),
            ]);

            try {
                Notificacion::create([
                    'usuario_id' => $request->receptor_id,
                    'tipo' => 'nuevo_mensaje',
                    'mensaje' => 'Has recibido un nuevo mensaje de ' . ($usuario->nombre['nombres'] ?? 'un usuario'),
                    'leido' => false,
                    'data' => [
                        'emisor_id' => $usuario->_id,
                        'mensaje_id' => $mensaje->_id,
                    ]
                ]);
            } catch (\Exception $e) {
                \Log::error('Error creando notificación: ' . $e->getMessage());
            }

            return response()->json($mensaje, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error al enviar: ' . $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ], 500);
        }
    }

    public function obtenerNutriologos()
    {
        $nutriologos = Usuario::where('rol', 'nutriologo')
            ->get(['_id', 'nombre', 'correo']);
        
        return response()->json($nutriologos);
    }

    public function obtenerPacientes()
    {
        $pacientes = Usuario::where('rol', 'paciente')
            ->get(['_id', 'nombre', 'correo', 'nutriologo_id']);
        
        return response()->json($pacientes);
    }
}
