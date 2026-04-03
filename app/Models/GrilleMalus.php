<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrilleMalus extends Model
{
    protected $table = 'grille_malus';

    protected $fillable = [
        'grille_id',
        'label',
        'deduction',
        'description',
        'ordre',
    ];

    /**
     * Retourne la grille de correction à laquelle appartient ce malus.
     */
    public function grille(): BelongsTo
    {
        return $this->belongsTo(GrilleCorrection::class, 'grille_id');
    }
}
