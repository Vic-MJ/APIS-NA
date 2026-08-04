<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionControlador extends Controller
{
    public function index()
    {
        $notificaciones = Notificacion::where('usuario_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($notificaciones);
    }

    public function marcarComoLeida($id)
    {
        $notificacion = Notificacion::where('_id', $id)
            ->where('usuario_id', (string)Auth::id())
            ->first();

        if ($notificacion) {
            $notificacion->update(['leido' => true]);
            return response()->json(['mensaje' => 'Notificación marcada como leída']);
        }

        return response()->json(['error' => 'Notificación no encontrada'], 404);
    }

    public function marcarTodasComoLeidas()
    {
        Notificacion::where('usuario_id', Auth::id())
            ->where('leido', false)
            ->update(['leido' => true]);

        return response()->json(['mensaje' => 'Todas las notificaciones marcadas como leídas']);
    }

    public function conteoNoLeidas()
    {
        $conteo = Notificacion::where('usuario_id', Auth::id())
            ->where('leido', false)
            ->count();

        return response()->json(['conteo' => $conteo]);
    }
    
    public function eliminar($id)
    {
        $notificacion = Notificacion::where('_id', $id)
            ->where('usuario_id', (string)Auth::id())
            ->first();

        if ($notificacion) {
            $notificacion->delete();
            return response()->json(['mensaje' => 'Notificación eliminada']);
        }

        return response()->json(['error' => 'Notificación no encontrada'], 404);
    }
}
