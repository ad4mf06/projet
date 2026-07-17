<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegionAdministrative extends Model
{
    protected $table = 'regions_administratives';

    protected $fillable = [
        'nom',
        'code_region',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne les métadonnées de projets musée associées à cette région.
     */
    public function museeMetas(): HasMany
    {
        return $this->hasMany(MuseeMeta::class, 'region_administrative_id');
    }
}
