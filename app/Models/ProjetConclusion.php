<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetConclusion extends Model
{
    protected $table = 'projet_conclusions';

    protected $fillable = [
        'projet_id',
        'user_id',
        'contenu',
    ];

    /**
     * Retourne le projet de recherche auquel appartient cette conclusion.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne l'étudiant auteur de cette conclusion.
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
