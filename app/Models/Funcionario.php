<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funcionario extends Model
{
    use HasFactory;

    protected $table = 'funcionario';
    protected $primaryKey = 'id_funcionario';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'tipo_documento',
        'numero_doc',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'telefono',
        'correo_cargo',
        'genero',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function vinculaciones(): HasMany
    {
        return $this->hasMany(Vinculacion::class, 'id_funcionario', 'id_funcionario');
    }
}
