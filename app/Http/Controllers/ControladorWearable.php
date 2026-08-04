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
        $wearable = Wearable::create($request->all());
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
        $wearable = Wearable::where('usuario_cliente.id_usuario', (int)$usuarioId)->orderBy('ultima_sincronizacion', 'desc')->first();
        return response()->json($wearable);
    }
}
