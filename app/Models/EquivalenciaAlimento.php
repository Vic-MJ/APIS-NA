<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class EquivalenciaAlimento extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'equivalencias_alimentos';

    protected $fillable = [
        'grupo_alimenticio',
        'alimentos', // [{ nombre, cantidad_equivalente, unidad, calorias }]
    ];

    protected $casts = [
        'alimentos' => 'array',
    ];
}
