<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuseePublication extends Model
{
    protected $table = 'musee_publications';

    // Valeurs possibles de la colonne `statut`
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_SOUMIS = 'soumis';

    public const STATUT_APPROUVE = 'approuve';

    public const STATUT_REJETE = 'rejete';

    protected $fillable = [
        'projet_recherche_id',
        'est_publie',
        'statut',
        'publie_le',
        'publie_par',
        'soumis_le',
        'raison_rejet',
        'est_copie_prof',
        'projet_original_id',
    ];

    protected function casts(): array
    {
        return [
            'est_publie' => 'boolean',
            'publie_le' => 'datetime',
            'soumis_le' => 'datetime',
            'est_copie_prof' => 'boolean',
        ];
    }

    /**
     * Indique si le musée est dans un état où les étudiants ne peuvent plus le modifier
     * (soumis en attente d'approbation, ou déjà approuvé et publié).
     */
    public function bloqueEditionEtudiants(): bool
    {
        return in_array($this->statut, [self::STATUT_SOUMIS, self::STATUT_APPROUVE]);
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
