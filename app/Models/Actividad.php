<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Actividad extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'actividades';

    protected $fillable = [
        'usuario_id',
        'nombre',
        'tipo',
        'duracion',
        'calorias',
        'fecha',
        'hora',
        'id_usuario'
    ];

    protected $casts = [
        'calorias' => 'float',
        'fecha' => 'date',
    ];
}
