<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Paciente extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'pacientes';

    protected $fillable = [
        'usuario_cliente', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre', // { nombres, apellido_p, apellido_m }
        'fecha_alta',
        'fecha_nacimiento',
        'edad',
        'peso',
        'medidas', // { cintura, brazos, piernas }
        'altura',
        'alergias', // [{ tipo, descripcion }]
    ];

    protected $casts = [
        'usuario_cliente' => 'array',
        'nombre' => 'array',
        'medidas' => 'array',
        'alergias' => 'array',
        'fecha_alta' => 'date',
        'fecha_nacimiento' => 'date',
        'edad' => 'integer',
        'peso' => 'float',
        'altura' => 'float',
    ];
}
