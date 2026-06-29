<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuseeBloc extends Model
{
    protected $table = 'musee_blocs';

    // Types de blocs disponibles dans l'éditeur musée
    public const TYPE_TEXTE = 'texte';

    public const TYPE_IMAGE = 'image';

    public const TYPE_CARROUSEL = 'carrousel';

    public const TYPE_VIDEO = 'video';

    public const TYPE_SEPARATEUR = 'separateur';

    protected $fillable = [
        'projet_recherche_id',
        'section_id',
        'type',
        'contenu',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'contenu' => 'array',
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne le projet de recherche auquel ce bloc appartient.
     */
    public function projetRecherche(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_recherche_id');
    }

    /**
     * Retourne la section de type projet à laquelle ce bloc est rattaché.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Indique si ce bloc contient du HTML avec des liens externes.
     *
     * Utilisé lors de la correction pour identifier rapidement
     * les blocs texte comportant des liens hors du site.
     */
    public function aDesLiensExternes(): bool
    {
        if ($this->type !== self::TYPE_TEXTE) {
            return false;
        }

        $html = $this->contenu['html'] ?? '';

        // Un lien externe est marqué avec data-externe="true" par l'éditeur Tiptap
        return str_contains($html, 'data-externe="true"');
    }
}
