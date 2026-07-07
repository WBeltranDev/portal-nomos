<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periodo extends Model
{
    use HasFactory;

    protected $table = 'periodo';
    protected $primaryKey = 'id_periodo';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario_apertura',
        'sistema',
        'anio',
        'semestre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_apertura', 'id_usuario');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'id_periodo', 'id_periodo');
    }
}
