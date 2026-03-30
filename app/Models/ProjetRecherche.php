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
        'correction_visible',
        'verrouille',
        'date_remise',
        'remis_le',
        'remises_multiples',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correction_visible' => 'boolean',
            'verrouille' => 'boolean',
            'remises_multiples' => 'boolean',
            'date_remise' => 'datetime',
            'remis_le' => 'datetime',
        ];
    }

    /**
     * Indique si le travail peut encore être remis par l'équipe.
     * Retourne false si déjà remis et que les remises multiples ne sont pas autorisées.
     */
    public function peutEtreRemis(): bool
    {
        if ($this->remis_le === null) {
            return true;
        }

        return (bool) $this->remises_multiples;
    }

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
     * Retourne les annotations inline de l'enseignant sur les sections du projet.
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(ProjetAnnotation::class, 'projet_id');
    }

    /**
     * Retourne les paragraphes de développement triés par ordre.
     */
    public function developpements(): HasMany
    {
        return $this->hasMany(ProjetDeveloppement::class, 'projet_id')->orderBy('ordre');
    }

    /**
     * Calcule le pourcentage de complétion du contenu partagé (hors conclusions).
     * Tient compte des champs fixes et de chaque paragraphe de développement (titre + contenu).
     *
     * @return int Pourcentage entre 0 et 100.
     */
    public function completion(): int
    {
        $champsFixes = [
            'titre_projet',
            'introduction_amener',
            'introduction_poser',
            'introduction_diviser',
        ];

        $remplisFixes = collect($champsFixes)
            ->filter(fn (string $f) => trim(strip_tags((string) ($this->$f ?? ''))) !== '')
            ->count();

        // Chaque paragraphe contribue 2 champs (titre + contenu)
        $developpements = $this->relationLoaded('developpements')
            ? $this->developpements
            : $this->developpements()->get();

        $totalDev = $developpements->count() * 2;
        $remplisDev = $developpements->reduce(function (int $carry, ProjetDeveloppement $dev): int {
            $carry += trim(strip_tags((string) ($dev->titre ?? ''))) !== '' ? 1 : 0;
            $carry += trim(strip_tags((string) ($dev->contenu ?? ''))) !== '' ? 1 : 0;

            return $carry;
        }, 0);

        $total = count($champsFixes) + $totalDev;

        if ($total === 0) {
            return 0;
        }

        return (int) round(($remplisFixes + $remplisDev) / $total * 100);
    }
}
