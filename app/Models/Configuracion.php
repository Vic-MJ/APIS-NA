<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Configuracion extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
        'tipo'
    ];

    public static function obtener($clave, $porDefecto = null)
    {
        $config = self::where('clave', $clave)->first();
        if (!$config) return $porDefecto;

        switch ($config->tipo) {
            case 'boolean': return (bool) $config->valor;
            case 'integer': return (int) $config->valor;
            default: return $config->valor;
        }
    }

    public static function establecer($clave, $valor, $tipo = 'string')
    {
        return self::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor, 'tipo' => $tipo]
        );
    }
}
