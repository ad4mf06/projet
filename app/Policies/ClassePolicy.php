<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;

class ClassePolicy
{
    /**
     * Détermine si l'utilisateur peut consulter la classe.
     */
    public function view(User $user, Classe $classe): bool
    {
        return $user->isAdmin() || $classe->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut modifier la classe ou gérer ses étudiants.
     */
    public function update(User $user, Classe $classe): bool
    {
        return $user->isAdmin() || $classe->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer la classe.
     */
    public function delete(User $user, Classe $classe): bool
    {
        return $user->isAdmin() || $classe->enseignant_id === $user->id;
    }
}
