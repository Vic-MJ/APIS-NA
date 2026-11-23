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
    private function marcarActividadActual(): void
    {
        $usuario = Auth::user();
        if ($usuario) {
            $usuario->update(['ultima_actividad' => now()->toIso8601String()]);
        }
    }

    private function estaUsuarioEnLinea($usuario): bool
    {
        if (!$usuario || empty($usuario->ultima_actividad)) {
            return false;
        }
        try {
            $ultima = \Carbon\Carbon::parse($usuario->ultima_actividad);
            return $ultima->diffInSeconds(now()) <= 75;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function obtenerConversaciones()
    {
        $this->marcarActividadActual();
        $usuarioId = (string) Auth::id();
        $emisores = Mensaje::where('receptor_id', $usuarioId)->pluck('emisor_id')->unique()->toArray();
        $receptores = Mensaje::where('emisor_id', $usuarioId)->pluck('receptor_id')->unique()->toArray();

        $usuarioIds = array_unique(array_merge($emisores, $receptores));

        $usuarios = Usuario::whereIn('_id', $usuarioIds)
            ->get(['_id', 'nombre', 'correo', 'rol', 'ultima_actividad']);

        $conversaciones = $usuarios->map(function ($usuario) use ($usuarioId) {
            $otroId = (string) $usuario->_id;
            $ultimoMensaje = Mensaje::where(function ($q) use ($usuarioId, $otroId) {
                $q->where('emisor_id', $usuarioId)->where('receptor_id', $otroId);
            })->orWhere(function ($q) use ($usuarioId, $otroId) {
                $q->where('emisor_id', $otroId)->where('receptor_id', $usuarioId);
            })->orderBy('created_at', 'desc')->first();

            $noLeidos = Mensaje::where('emisor_id', $otroId)
                ->where('receptor_id', $usuarioId)
                ->where('leido', false)
                ->count();

            $enLinea = $this->estaUsuarioEnLinea($usuario);
            $usuarioArr = $usuario->toArray();
            $usuarioArr['en_linea'] = $enLinea;

            return [
                'usuario' => $usuarioArr,
                'en_linea' => $enLinea,
                'ultimo_mensaje' => $ultimoMensaje,
                'no_leidos' => $noLeidos
            ];
        });

        return response()->json($conversaciones->sortByDesc(function ($c) {
            return $c['ultimo_mensaje'] ? $c['ultimo_mensaje']->created_at : 0;
        })->values());
    }

    private function resolverUsuarioODestinatario(string $idPosible): ?Usuario
    {
        $u = Usuario::where('_id', $idPosible)->first();
        if ($u) {
            return $u;
        }

        // Puede ser un _id de la colección nutriologos
        $nutri = \App\Models\Nutriologo::where('_id', $idPosible)->first();
        if ($nutri) {
            $uData = $nutri->usuario;
            if (is_string($uData)) {
                $uData = json_decode($uData, true);
            }
            $idReal = is_array($uData) ? ($uData['id_usuario'] ?? null) : null;
            if ($idReal) {
                $u = Usuario::where('_id', (string) $idReal)->first();
                if ($u) {
                    return $u;
                }
            }
        }

        // Puede ser un _id de la colección pacientes
        $pac = \App\Models\Paciente::where('_id', $idPosible)->first();
        if ($pac) {
            $uCliente = $pac->usuario_cliente;
            if (is_string($uCliente)) {
                $uCliente = json_decode($uCliente, true);
            }
            $idReal = is_array($uCliente) ? ($uCliente['id_usuario'] ?? null) : null;
            if ($idReal) {
                $u = Usuario::where('_id', (string) $idReal)->first();
                if ($u) {
                    return $u;
                }
            }
        }

        return null;
    }

    public function obtenerMensajes($otroUsuarioId)
    {
        $this->marcarActividadActual();
        $usuarioId = (string) Auth::id();
        $otroResuelto = $this->resolverUsuarioODestinatario((string) $otroUsuarioId);
        $otroIdStr = $otroResuelto ? (string) $otroResuelto->_id : (string) $otroUsuarioId;

        $idsPosiblesOtro = array_values(array_unique([$otroIdStr, (string) $otroUsuarioId]));

        $mensajes = Mensaje::where(function ($q) use ($usuarioId, $idsPosiblesOtro) {
            $q->where('emisor_id', $usuarioId)->whereIn('receptor_id', $idsPosiblesOtro);
        })->orWhere(function ($q) use ($usuarioId, $idsPosiblesOtro) {
            $q->whereIn('emisor_id', $idsPosiblesOtro)->where('receptor_id', $usuarioId);
        })->orderBy('created_at', 'asc')->get();

        Mensaje::whereIn('emisor_id', $idsPosiblesOtro)
            ->where('receptor_id', $usuarioId)
            ->where('leido', false)
            ->update(['leido' => true]);

        return response()->json($mensajes);
    }

    public function estadoUsuario($otroUsuarioId)
    {
        $this->marcarActividadActual();
        $otro = $this->resolverUsuarioODestinatario((string) $otroUsuarioId);
        return response()->json([
            'usuario_id' => $otro ? (string) $otro->_id : (string) $otroUsuarioId,
            'en_linea' => $this->estaUsuarioEnLinea($otro),
            'ultima_actividad' => $otro?->ultima_actividad,
        ]);
    }

    public function enviarMensaje(Request $request)
    {
        try {
            $request->validate([
                'receptor_id' => 'required|string',
                'contenido' => 'required|string',
            ]);

            $receptor = $this->resolverUsuarioODestinatario((string) $request->receptor_id);
            if (!$receptor) {
                return response()->json(['mensaje' => 'El receptor no existe: ' . $request->receptor_id], 404);
            }

            $idReceptorReal = (string) $receptor->_id;
            $usuario = Auth::user();
            $this->marcarActividadActual();

            $mensaje = Mensaje::create([
                'emisor_id' => (string) $usuario->_id,
                'receptor_id' => $idReceptorReal,
                'contenido' => $request->contenido,
                'leido' => false,
                'fecha_envio' => now(),
            ]);

            try {
                Notificacion::create([
                    'usuario_id' => $idReceptorReal,
                    'tipo' => 'nuevo_mensaje',
                    'mensaje' => 'Has recibido un nuevo mensaje de ' . ($usuario->nombre['nombres'] ?? 'un usuario'),
                    'leido' => false,
                    'data' => [
                        'emisor_id' => (string) $usuario->_id,
                        'mensaje_id' => (string) $mensaje->_id,
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
            ->get(['_id', 'nombre', 'correo', 'nutriologo_id', 'id_nutriologo']);
        
        return response()->json($pacientes);
    }
}
