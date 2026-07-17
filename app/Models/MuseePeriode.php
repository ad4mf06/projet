<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MuseePeriode extends Model
{
    protected $table = 'musee_periodes';

    protected $fillable = [
        'cours_id',
        'enseignant_id',
        'nom',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne le cours auquel cette période est rattachée.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    /**
     * Retourne l'enseignant qui a créé cette période.
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    /**
     * Retourne les métadonnées de projets musée utilisant cette période.
     */
    public function museeMetas(): HasMany
    {
        return $this->hasMany(MuseeMeta::class, 'periode_id');
    }
}
