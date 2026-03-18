<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeNote extends Model
{
    protected $fillable = [
        'groupe_id',
        'user_id',
        'contenu',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }
}
