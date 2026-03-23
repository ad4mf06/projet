<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetCommentaire extends Model
{
    protected $fillable = [
        'projet_id',
        'champ',
        'contenu',
        'created_by',
    ];

    /**
     * Retourne le projet de recherche auquel appartient ce commentaire.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne l'enseignant auteur du commentaire.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
