<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuseeVideoSegment extends Model
{
    protected $table = 'musee_video_segments';

    protected $fillable = [
        'bloc_id',
        'section_id',
        'debut_secondes',
        'fin_secondes',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'debut_secondes' => 'integer',
            'fin_secondes' => 'integer',
        ];
    }

    /**
     * Retourne le bloc musée auquel ce segment est rattaché.
     */
    public function bloc(): BelongsTo
    {
        return $this->belongsTo(MuseeBloc::class, 'bloc_id');
    }

    /**
     * Retourne la section du type projet liée à ce segment.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Formate les secondes en mm:ss pour l'affichage.
     */
    public function debutFormate(): string
    {
        return sprintf('%d:%02d', intdiv($this->debut_secondes, 60), $this->debut_secondes % 60);
    }

    /**
     * Formate les secondes de fin en mm:ss pour l'affichage.
     */
    public function finFormate(): string
    {
        return sprintf('%d:%02d', intdiv($this->fin_secondes, 60), $this->fin_secondes % 60);
    }
}
