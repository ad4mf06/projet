<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EpoqueHistorique extends Model
{
    protected $table = 'epoques_historiques';

    protected $fillable = [
        'nom',
        'annee_debut',
        'annee_fin',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'annee_debut' => 'integer',
            'annee_fin' => 'integer',
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne les métadonnées de projets musée associées à cette époque.
     */
    public function museeMetas(): HasMany
    {
        return $this->hasMany(MuseeMeta::class, 'epoque_historique_id');
    }
}
