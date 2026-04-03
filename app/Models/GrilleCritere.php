<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrilleCritere extends Model
{
    protected $table = 'grille_criteres';

    protected $fillable = [
        'grille_id',
        'label',
        'ponderation',
        'ordre',
    ];

    /**
     * Retourne la grille de correction à laquelle appartient ce critère.
     */
    public function grille(): BelongsTo
    {
        return $this->belongsTo(GrilleCorrection::class, 'grille_id');
    }
}
