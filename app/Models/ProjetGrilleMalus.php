<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetGrilleMalus extends Model
{
    protected $table = 'projet_grille_malus';

    protected $fillable = [
        'projet_id',
        'user_id',
        'malus_id',
        'applique',
    ];

    protected function casts(): array
    {
        return ['applique' => 'boolean'];
    }

    /**
     * Retourne le projet de recherche auquel est rattaché ce malus.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne l'étudiant concerné par ce malus.
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Retourne la définition du malus (label et déduction).
     */
    public function malus(): BelongsTo
    {
        return $this->belongsTo(GrilleMalus::class, 'malus_id');
    }
}
