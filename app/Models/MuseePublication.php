<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuseePublication extends Model
{
    protected $table = 'musee_publications';

    protected $fillable = [
        'projet_recherche_id',
        'est_publie',
        'publie_le',
        'publie_par',
        'est_copie_prof',
        'projet_original_id',
    ];

    protected function casts(): array
    {
        return [
            'est_publie' => 'boolean',
            'publie_le' => 'datetime',
            'est_copie_prof' => 'boolean',
        ];
    }

    /**
     * Retourne le projet de recherche associé à cet enregistrement de publication.
     */
    public function projetRecherche(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_recherche_id');
    }

    /**
     * Retourne l'enseignant qui a effectué la publication.
     */
    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    /**
     * Retourne le projet étudiant original si ce projet est une copie prof.
     */
    public function projetOriginal(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_original_id');
    }
}
