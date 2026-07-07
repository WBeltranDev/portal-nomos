<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompromisoMeta extends Model
{
    use HasFactory;

    protected $table = 'compromiso_meta';
    protected $primaryKey = ['id_compromiso', 'meta'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_compromiso',
        'meta',
    ];

    public function compromiso(): BelongsTo
    {
        return $this->belongsTo(Compromiso::class, 'id_compromiso', 'id_compromiso');
    }
}
