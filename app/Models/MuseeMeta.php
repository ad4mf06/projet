<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MuseeMeta extends Model
{
    protected $table = 'musee_meta';

    protected $fillable = [
        'projet_recherche_id',
        'periode_id',
        'thematique_id',
        'region_id',
        'slug',
        'intro_texte',
        'intro_image_path',
        'entete_image_path',
        'entete_titre',
        'entete_sous_titre',
        'entete_overlay_couleur',
        'entete_image_position',
    ];

    /**
     * Retourne le projet de recherche associé à ces métadonnées.
     */
    public function projetRecherche(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_recherche_id');
    }

    /**
     * Retourne la période historique sélectionnée pour ce projet.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(MuseePeriode::class, 'periode_id');
    }

    /**
     * Retourne la thématique sélectionnée pour ce projet.
     */
    public function thematique(): BelongsTo
    {
        return $this->belongsTo(Thematique::class, 'thematique_id');
    }

    /**
     * Retourne la région géographique sélectionnée pour ce projet.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(MuseeRegion::class, 'region_id');
    }

    /**
     * Génère un slug unique à partir d'un titre et d'un suffixe aléatoire
     * pour garantir l'unicité dans la galerie publique.
     */
    public static function genererSlug(string $titre): string
    {
        $base = Str::slug($titre);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
