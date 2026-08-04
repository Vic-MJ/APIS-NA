<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Laravel\Sanctum\Contracts\HasAbilities;

class TokenAccesoPersonal extends Model implements HasAbilities
{
    protected $connection = 'mongodb';
    protected $collection = 'personal_access_tokens';

    protected $fillable = [
        'nombre',
        'token',
        'habilidades',
        'expira_en',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'habilidades' => 'array',
            'ultimo_uso_en' => 'datetime',
            'expira_en' => 'datetime',
        ];
    }

    public function tokenable()
    {
        return $this->morphTo('tokenable');
    }

    public function can($habilidad)
    {
        return in_array('*', $this->habilidades ?? []) ||
               array_key_exists($habilidad, array_flip($this->habilidades ?? []));
    }

    public function cant($habilidad)
    {
        return ! $this->can($habilidad);
    }

    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            return static::where('token', hash('sha256', $token))->first();
        }

        [$id, $token] = explode('|', $token, 2);

        if ($instancia = static::find($id)) {
            return hash_equals($instancia->token, hash('sha256', $token)) ? $instancia : null;
        }

        return null;
    }
}
