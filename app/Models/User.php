<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'rol',
        'activo',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rol' => 'string',
            'activo' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function funcionario(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Funcionario::class, 'id_usuario', 'id_usuario');
    }
}
