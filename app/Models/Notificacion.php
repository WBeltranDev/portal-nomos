<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificacion';
    protected $primaryKey = 'id_notificacion';

    protected $fillable = [
        'tipo',
        'titulo',
        'mensaje',
        'seccion',
        'leida',
    ];

    protected $casts = [
        'leida' => 'boolean',
    ];
}
