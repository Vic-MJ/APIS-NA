<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;

    protected $collection = 'notificaciones';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'mensaje',
        'leido',
        'data',
    ];

    protected $casts = [
        'leido' => 'boolean',
        'data' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
