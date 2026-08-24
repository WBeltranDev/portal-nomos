<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    use HasFactory;

    protected $table = 'cargo';
    protected $primaryKey = 'id_cargo';

    protected $fillable = [
        'nombre',
        'codigo_cargo',
        'grado_cargo',
        'nivel_jerarquico',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'codigo_cargo' => 'integer',
        'grado_cargo' => 'integer',
    ];
}
