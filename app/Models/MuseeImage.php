<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
     *
     * On passe par Storage::disk('public')->url() plutôt que asset() pour que
     * l'URL soit automatiquement générée depuis le bucket S3 quand FILESYSTEM_DISK=s3
     * est configuré en production, sans aucun changement de code.
     * Le chemin en base contient un préfixe "storage/" hérité de l'écriture initiale ;
     * on le retire pour obtenir le chemin relatif attendu par le disque.
     */
    public function getUrlAttribute(): string
    {
        $relativePath = preg_replace('#^storage/#', '', $this->path);

        return Storage::disk('public')->url($relativePath);
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
