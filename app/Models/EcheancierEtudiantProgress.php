<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcheancierEtudiantProgress extends Model
{
    protected $table = 'echeancier_etudiant_progress';

    protected $fillable = [
        'echeancier_etape_id',
        'user_id',
        'is_done',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    /**
     * Retourne l'étape de l'échéancier liée à cette progression.
     */
    public function etape(): BelongsTo
    {
        return $this->belongsTo(EcheancierEtape::class, 'echeancier_etape_id');
    }

    /**
     * Retourne l'étudiant lié à cette progression.
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
