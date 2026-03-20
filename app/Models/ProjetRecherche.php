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
     * Calcule le pourcentage de complétion du contenu partagé (hors conclusions).
     *
     * @return int Pourcentage entre 0 et 100.
     */
    public function completion(): int
    {
        $champs = [
            'titre_projet',
            'introduction_amener',
            'introduction_poser',
            'introduction_diviser',
            'dev_1_titre', 'dev_1_contenu',
            'dev_2_titre', 'dev_2_contenu',
            'dev_3_titre', 'dev_3_contenu',
            'dev_4_titre', 'dev_4_contenu',
            'dev_5_titre', 'dev_5_contenu',
        ];

        $remplis = collect($champs)
            ->filter(fn (string $f) => trim(strip_tags((string) ($this->$f ?? ''))) !== '')
            ->count();

        return (int) round($remplis / count($champs) * 100);
    }
}
