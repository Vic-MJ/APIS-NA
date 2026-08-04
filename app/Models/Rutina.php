<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Rutina extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rutinas';

    protected $fillable = [
        'usuario_cliente', // { id_usuario, nombre: { nombres, apellido_p, apellido_m } }
        'nombre', // { nombres, apellido_p, apellido_m }
        'ejercicio',
        'objetivo',
        'frecuencia_semanal',
        'nivel_dificultad',
        'calorias_quemadas',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'usuario_cliente' => 'array',
        'nombre' => 'array',
        'calorias_quemadas' => 'float',
        'frecuencia_semanal' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];
}
