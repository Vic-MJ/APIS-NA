<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Nutriologo extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'nutriologos';

    protected $fillable = [
        'usuario', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre', // { nombres, apellido_p, apellido_m }
        'cedula_profesional',
        'especialidad',
        'universidad',
        'pacientes', // [id_usuario_cliente]
    ];

    protected $casts = [
        'usuario' => 'array',
        'nombre' => 'array',
        'pacientes' => 'array',
    ];
}
