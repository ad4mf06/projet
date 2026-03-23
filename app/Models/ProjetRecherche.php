<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjetRecherche extends Model
{
    protected $table = 'projets_recherche';

    protected $fillable = [
        'groupe_id',
        'dev_count',
        'titre_projet',
        'introduction_amener',
        'introduction_poser',
        'introduction_diviser',
        'dev_1_titre',
        'dev_1_contenu',
        'dev_2_titre',
        'dev_2_contenu',
        'dev_3_titre',
        'dev_3_contenu',
        'dev_4_titre',
        'dev_4_contenu',
        'dev_5_titre',
        'dev_5_contenu',
    ];

    /**
     * Retourne le groupe auquel appartient ce projet.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne les conclusions individuelles des membres de l'équipe.
     */
    public function conclusions(): HasMany
    {
        return $this->hasMany(ProjetConclusion::class, 'projet_id');
    }

    /**
     * Retourne les commentaires de l'enseignant par champ.
     */
    public function commentaires(): HasMany
    {
        return $this->hasMany(ProjetCommentaire::class, 'projet_id');
    }

    /**
     * Retourne les notes de la grille de correction.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjetNote::class, 'projet_id');
    }

    /**
     * Calcule le pourcentage de complétion du contenu partagé (hors conclusions).
     * Tient compte uniquement des paragraphes de développement actifs (dev_count).
     *
     * @return int Pourcentage entre 0 et 100.
     */
    public function completion(): int
    {
        $devCount = (int) ($this->dev_count ?? 1);

        $champsFixes = [
            'titre_projet',
            'introduction_amener',
            'introduction_poser',
            'introduction_diviser',
        ];

        $champsDevActifs = [];
        for ($i = 1; $i <= $devCount; $i++) {
            $champsDevActifs[] = "dev_{$i}_titre";
            $champsDevActifs[] = "dev_{$i}_contenu";
        }

        $champs = array_merge($champsFixes, $champsDevActifs);

        $remplis = collect($champs)
            ->filter(fn (string $f) => trim(strip_tags((string) ($this->$f ?? ''))) !== '')
            ->count();

        return (int) round($remplis / count($champs) * 100);
    }
}
