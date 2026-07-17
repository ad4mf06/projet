<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuseeTemplate extends Model
{
    protected $table = 'musee_templates';

    protected $fillable = [
        'type_projet_id',
        'font_titre_page',
        'font_sous_titre',
        'font_titre_section',
        'font_corps',
        'font_legende',
        'couleur_fond',
        'couleur_titre',
        'couleur_corps',
        'couleur_accent',
        'couleur_lien_externe',
        'palette',
        'theme',
    ];

    /**
     * Retourne le type de projet auquel ce template est rattaché.
     */
    public function typeProjet(): BelongsTo
    {
        return $this->belongsTo(TypeProjet::class, 'type_projet_id');
    }

    /**
     * Retourne les variables CSS générées depuis ce template,
     * à injecter dans le layout public du musée.
     *
     * @return array<string, string>
     */
    public function toCssVariables(): array
    {
        return [
            '--musee-font-titre-page' => $this->font_titre_page,
            '--musee-font-sous-titre' => $this->font_sous_titre,
            '--musee-font-titre-section' => $this->font_titre_section,
            '--musee-font-corps' => $this->font_corps,
            '--musee-font-legende' => $this->font_legende,
            '--musee-couleur-fond' => $this->couleur_fond,
            '--musee-couleur-titre' => $this->couleur_titre,
            '--musee-couleur-corps' => $this->couleur_corps,
            '--musee-couleur-accent' => $this->couleur_accent,
            '--musee-couleur-lien-externe' => $this->couleur_lien_externe,
        ];
    }
}
