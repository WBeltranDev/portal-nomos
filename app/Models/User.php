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
        'contrasena_hash',
        'rol',
        'id_empleado',
    ];

    protected $hidden = [
        'contrasena_hash',
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
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->contrasena_hash;
    }
}
