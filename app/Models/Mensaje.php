<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Mensaje extends Model
{
    use HasFactory;

    protected $collection = 'mensajes';

    protected $fillable = [
        'emisor_id',
        'receptor_id',
        'contenido',
        'leido',
        'fecha_envio',
    ];

    protected $casts = [
        'leido' => 'boolean',
        'fecha_envio' => 'datetime',
    ];

    public function emisor()
    {
        return $this->belongsTo(Usuario::class, 'emisor_id');
    }

    public function receptor()
    {
        return $this->belongsTo(Usuario::class, 'receptor_id');
    }
}
