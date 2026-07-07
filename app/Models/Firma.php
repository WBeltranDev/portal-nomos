<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Firma extends Model
{
    use HasFactory;

    protected $table = 'firma';
    protected $primaryKey = 'id_firma';
    public $timestamps = false;

    protected $fillable = [
        'id_evaluacion',
        'tipo_firma',
        'id_vinc_firmante',
        'fecha_firma',
        'renuencia',
    ];

    protected $casts = [
        'renuencia' => 'boolean',
    ];

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion', 'id_evaluacion');
    }

    public function firmante(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class, 'id_vinc_firmante', 'id_vinculacion');
    }
}
