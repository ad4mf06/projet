<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClasseDocument extends Model
{
    use HasPublicFile;

    protected $fillable = [
        'classe_id',
        'enseignant_id',
        'nom_original',
        'file_path',
        'type',
        'taille',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset($this->file_path);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
