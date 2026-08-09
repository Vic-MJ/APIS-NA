<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function tokens()
    {
        return $this->morphMany(TokenAccesoPersonal::class, 'tokenable');
    }

    public function createToken(string $nombre, array $habilidades = ['*'], ?\DateTimeInterface $expiraEn = null)
    {
        $tokenTextoPlano = \Illuminate\Support\Str::random(40);

        $token = $this->tokens()->create([
            'nombre' => $nombre,
            'token' => hash('sha256', $tokenTextoPlano),
            'habilidades' => $habilidades,
            'expira_en' => $expiraEn,
        ]);

        return new class($token, $token->_id.'|'.$tokenTextoPlano) {
            public function __construct(public $accessToken, public string $plainTextToken) {}
            public function toArray() { return ['accessToken' => $this->accessToken, 'plainTextToken' => $this->plainTextToken]; }
        };
    }

    protected $collection = 'usuarios';

    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'rol',
        'cedula',
        'tipo_cedula',
        'ine_frente',
        'ine_reverso',
        'estado_validacion',
        'tipo_paciente', // 'free' o 'pro'
        'nutriologo_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'nombre' => 'array',
        ];
    }

    public function getEmailAttribute()
    {
        return $this->correo;
    }

    public function setEmailAttribute($value)
    {
        $this->correo = $value;
    }

    public function getNombreCompletoAttribute()
    {
        if (is_array($this->nombre)) {
            return trim(($this->nombre['nombres'] ?? '') . ' ' . ($this->nombre['apellido_p'] ?? ''));
        }
        return $this->correo;
    }
}
