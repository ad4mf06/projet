<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetDeveloppement extends Model
{
    protected $table = 'projet_developpements';

    protected $fillable = [
        'projet_id',
        'ordre',
        'titre',
        'contenu',
    ];

    /**
     * Retourne le projet de recherche auquel appartient ce paragraphe.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }
}
