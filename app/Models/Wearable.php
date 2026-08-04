<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Wearable extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'wearables';

    protected $fillable = [
        'usuario_cliente', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre', // { nombres, apellido_p, apellido_m }
        'marca_dispositivo',
        'metricas_diarias', // { pasos, kcal_quemadas, horas_sueno, ritmo_cardiaco_promedio }
        'ultima_sincronizacion',
    ];

    protected $casts = [
        'usuario_cliente' => 'array',
        'nombre' => 'array',
        'metricas_diarias' => 'array',
        'ultima_sincronizacion' => 'datetime',
    ];
}
