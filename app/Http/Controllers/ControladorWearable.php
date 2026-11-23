<?php

namespace App\Http\Controllers;

use App\Models\Wearable;
use Illuminate\Http\Request;

class ControladorWearable extends Controller
{
    public function index()
    {
        return response()->json(Wearable::all());
    }

    public function store(Request $request)
    {
        $datos = $request->all();
        if (!isset($datos['usuario_id']) && auth()->check()) {
            $datos['usuario_id'] = (string) auth()->id();
        }
        if (!isset($datos['ultima_sincronizacion'])) {
            $datos['ultima_sincronizacion'] = now();
        }
        $wearable = Wearable::create($datos);
        return response()->json($wearable, 201);
    }

    public function show($id)
    {
        return response()->json(Wearable::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $wearable = Wearable::findOrFail($id);
        $wearable->update($request->all());
        return response()->json($wearable);
    }

    public function destroy($id)
    {
        Wearable::destroy($id);
        return response()->json(['mensaje' => 'Wearable eliminado']);
    }

    public function ultimaSincronizacion($usuarioId)
    {
        $wearable = Wearable::where('usuario_id', (string) $usuarioId)
            ->orWhere('usuario_cliente.id_usuario', (string) $usuarioId)
            ->orWhere('usuario_cliente.id_usuario', (int) $usuarioId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$wearable) {
            $wearable = Wearable::orderBy('created_at', 'desc')->first();
        }

        return response()->json($wearable);
    }
}
