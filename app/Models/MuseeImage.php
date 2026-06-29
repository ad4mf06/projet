<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuseeImage extends Model
{
    protected $table = 'musee_images';

    protected $fillable = [
        'projet_recherche_id',
        'path',
        'alt',
        'legende',
        'mime_type',
        'taille',
        'crop_data',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'taille' => 'integer',
            'crop_data' => 'array',
        ];
    }

    /**
     * Retourne le projet de recherche propriétaire de cette image.
     */
    public function projetRecherche(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_recherche_id');
    }

    /**
     * Retourne l'URL publique de l'image.
     */
    public function getUrlAttribute(): string
    {
        return asset($this->path);
    }

    /**
     * Retourne les styles CSS `object-position` calculés depuis les données de crop.
     *
     * Quand une image est recadrée, on utilise CSS pour simuler le crop
     * sans avoir à régénérer l'image côté serveur.
     */
    public function cropCssStyle(): string
    {
        if (empty($this->crop_data)) {
            return '';
        }

        $x = $this->crop_data['x'] ?? 50;
        $y = $this->crop_data['y'] ?? 50;

        return "object-fit: cover; object-position: {$x}% {$y}%;";
    }
}
