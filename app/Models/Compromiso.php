<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compromiso extends Model
{
    use HasFactory;

    protected $table = 'compromiso';
    protected $primaryKey = 'id_compromiso';
    public $timestamps = false;

    protected $fillable = [
        'id_evaluacion',
        'numero_orden',
        'descripcion',
        'porcentaje_peso',
        'calificacion_sem1',
        'calificacion_sem2',
        'calificacion_definitiva',
    ];

    protected $casts = [
        'porcentaje_peso' => 'float',
        'calificacion_sem1' => 'float',
        'calificacion_sem2' => 'float',
        'calificacion_definitiva' => 'float',
    ];

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion', 'id_evaluacion');
    }

    public function metas(): HasMany
    {
        return $this->hasMany(CompromisoMeta::class, 'id_compromiso', 'id_compromiso');
    }
}
