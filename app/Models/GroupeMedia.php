<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeMedia extends Model
{
    use HasPublicFile;

    protected $table = 'groupe_medias';

    protected $fillable = [
        'groupe_id',
        'user_id',
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

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
