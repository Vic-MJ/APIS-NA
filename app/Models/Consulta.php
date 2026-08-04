<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Consulta extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'consultas';

    protected $fillable = [
        'usuario_cliente', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre', // { nombres, apellido_p, apellido_m }
        'nutriologo', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre_nutriologo', // nombre extra segun imagen
        'especialidad',
        'fecha_hora',
        'tipo',
        'estatus',
        'notas_nutriologo',
    ];

    protected $casts = [
        'usuario_cliente' => 'array',
        'nombre' => 'array',
        'nutriologo' => 'array',
        'fecha_hora' => 'datetime',
    ];
}
