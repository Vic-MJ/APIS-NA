<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Comida extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'comidas';

    protected $fillable = [
        'usuario_id',
        'id_dieta',
        'dia',
        'momentos', // [{ tiempo, hora_sugerida, descripcion, calorias, foto, porcioneso, cantidad, unidad, grupo_alimenticio }] }]
    ];

    protected $casts = [
        'momentos' => 'array',
        'id_dieta' => 'string',
    ];
}
