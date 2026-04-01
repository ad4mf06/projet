<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetVoteRemise extends Model
{
    protected $table = 'projet_votes_remise';

    protected $fillable = [
        'projet_id',
        'user_id',
        'vote',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vote' => 'boolean',
        ];
    }

    /**
     * Retourne le projet de recherche auquel appartient ce vote.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne l'étudiant ayant voté.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
