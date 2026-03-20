<?php

namespace App\Policies;

use App\Models\Thematique;
use App\Models\User;

class ThematiquePolicy
{
    /**
     * Détermine si l'utilisateur peut modifier la thématique.
     */
    public function update(User $user, Thematique $thematique): bool
    {
        return $user->isAdmin() || $thematique->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer la thématique.
     */
    public function delete(User $user, Thematique $thematique): bool
    {
        return $user->isAdmin() || $thematique->enseignant_id === $user->id;
    }
}
