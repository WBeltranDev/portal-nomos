<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegacion extends Model
{
    use HasFactory;

    protected $table = 'delegacion';
    protected $primaryKey = 'id_delegacion';
    public $timestamps = true;

    protected $fillable = [
        'id_vinc_delegante',
        'id_vinc_delegado',
        'motivo',
        'acto_administrativo',
        'acto_administrativo_numero',
        'acto_administrativo_fecha',
        'acto_administrativo_url',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'id_usuario_registra',
        'detalle_transferencia',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'acto_administrativo_fecha' => 'date',
        'detalle_transferencia' => 'array',
    ];

    public function delegante(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class, 'id_vinc_delegante', 'id_vinculacion');
    }

    public function delegado(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class, 'id_vinc_delegado', 'id_vinculacion');
    }
}
