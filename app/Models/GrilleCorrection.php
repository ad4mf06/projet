<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrilleCorrection extends Model
{
    protected $table = 'grilles_correction';

    protected $fillable = [
        'classe_id',
        'nom',
        'description',
    ];

    /**
     * Retourne la classe à laquelle appartient cette grille.
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    /**
     * Retourne les critères de cette grille, triés par ordre d'affichage.
     */
    public function criteres(): HasMany
    {
        return $this->hasMany(GrilleCritere::class, 'grille_id')->orderBy('ordre');
    }

    /**
     * Retourne les malus de cette grille, triés par ordre d'affichage.
     */
    public function malus(): HasMany
    {
        return $this->hasMany(GrilleMalus::class, 'grille_id')->orderBy('ordre');
    }
}
