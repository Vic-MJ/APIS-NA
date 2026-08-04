<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Actividad extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'actividades';

    protected $fillable = [
        'nombre',
        'tipo',
        'duracion',
        'calorias',
        'fecha',
        'hora'
    ];

    protected $casts = [
        'calorias' => 'float',
        'fecha' => 'date',
    ];
}
