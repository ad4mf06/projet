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
        'epoque_historique_id',
        'thematique_id',
        'region_administrative_id',
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
     * Retourne l'époque historique du Québec sélectionnée pour ce projet.
     */
    public function epoque(): BelongsTo
    {
        return $this->belongsTo(EpoqueHistorique::class, 'epoque_historique_id');
    }

    /**
     * Retourne la thématique pédagogique sélectionnée pour ce projet.
     */
    public function thematique(): BelongsTo
    {
        return $this->belongsTo(Thematique::class, 'thematique_id');
    }

    /**
     * Retourne la région administrative du Québec sélectionnée pour ce projet.
     */
    public function regionAdministrative(): BelongsTo
    {
        return $this->belongsTo(RegionAdministrative::class, 'region_administrative_id');
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
