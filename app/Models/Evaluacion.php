<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluacion extends Model
{
    use HasFactory;

    protected $table = 'evaluacion';
    protected $primaryKey = 'id_evaluacion';
    public $timestamps = false;

    protected $fillable = [
        'id_periodo',
        'id_vinc_evaluado',
        'id_vinc_evaluador',
        'id_vinc_suplente',
        'tipo_evaluacion',
        'fase_actual',
        'concertacion_firmada',
        'estado',
        'calificacion_final',
        'categoria_final',
        'es_parcial',
        'dias_laborados',
    ];

    protected $casts = [
        'concertacion_firmada' => 'boolean',
        'es_parcial' => 'boolean',
        'calificacion_final' => 'float',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'id_periodo', 'id_periodo');
    }

    public function evaluado(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class, 'id_vinc_evaluado', 'id_vinculacion');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class, 'id_vinc_evaluador', 'id_vinculacion');
    }

    public function suplente(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class, 'id_vinc_suplente', 'id_vinculacion');
    }

    public function compromisos(): HasMany
    {
        return $this->hasMany(Compromiso::class, 'id_evaluacion', 'id_evaluacion');
    }

    public function firmas(): HasMany
    {
        return $this->hasMany(Firma::class, 'id_evaluacion', 'id_evaluacion');
    }
}
