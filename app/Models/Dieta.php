<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Dieta extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'dietas';

    protected $fillable = [
        'usuario_cliente', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre', // { nombres, apellido_p, apellido_m }
        'nutriologo', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre_nutriologo',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'agua_objetivo_litros',
    ];

    protected $casts = [
        'usuario_cliente' => 'array',
        'nombre' => 'array',
        'nutriologo' => 'array',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'agua_objetivo_litros' => 'float',
    ];
}
