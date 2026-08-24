<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpedimentoRecusacion extends Model
{
    use HasFactory;

    protected $table = 'impedimento_recusacion';
    protected $primaryKey = 'id_impedimento';

    protected $fillable = [
        'id_evaluacion',
        'id_vinc_solicitante',
        'tipo',
        'motivo',
        'evidencia_url',
        'estado',
        'respuesta_admin',
        'id_usuario_admin',
    ];
}
