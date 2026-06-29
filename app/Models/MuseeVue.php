<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MuseeVue extends Model
{
    protected $table = 'musee_vues';

    public $timestamps = false;

    protected $fillable = [
        'projet_recherche_id',
        'ip_hash',
        'vue_le',
    ];

    protected function casts(): array
    {
        return [
            'vue_le' => 'datetime',
        ];
    }

    /**
     * Retourne le projet de recherche consulté.
     */
    public function projetRecherche(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_recherche_id');
    }

    /**
     * Enregistre une vue en évitant les doublons pour la même IP sur 24h.
     *
     * L'IP est hashée avant d'être stockée pour respecter la vie privée.
     */
    public static function enregistrer(int $projetRechercheId, string $ip): void
    {
        $ipHash = hash('sha256', $ip);
        $depuis = Carbon::now()->subHours(24);

        $dejaVu = static::where('projet_recherche_id', $projetRechercheId)
            ->where('ip_hash', $ipHash)
            ->where('vue_le', '>=', $depuis)
            ->exists();

        if (! $dejaVu) {
            static::create([
                'projet_recherche_id' => $projetRechercheId,
                'ip_hash' => $ipHash,
                'vue_le' => Carbon::now(),
            ]);
        }
    }
}
