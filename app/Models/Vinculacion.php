<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vinculacion extends Model
{
    use HasFactory;

    protected $table = 'vinculacion';
    protected $primaryKey = 'id_vinculacion';
    public $timestamps = false;

    protected $fillable = [
        'id_funcionario',
        'cargo',
        'codigo_cargo',
        'grado_cargo',
        'nivel_jerarquico',
        'area',
        'tipo_vinculacion',
        'sistema_evaluacion',
        'es_evaluador',
        'aplica_eje_misional',
        'fecha_ingreso',
        'fecha_retiro',
        'resolucion',
        'activa',
    ];

    protected $casts = [
        'es_evaluador' => 'boolean',
        'aplica_eje_misional' => 'boolean',
        'activa' => 'boolean',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'id_funcionario', 'id_funcionario');
    }

    public function evaluacionesEvaluado(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'id_vinc_evaluado', 'id_vinculacion');
    }

    public function evaluacionesEvaluador(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'id_vinc_evaluador', 'id_vinculacion');
    }
}
