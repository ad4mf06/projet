<?php

namespace App\Policies;

use App\Models\Groupe;
use App\Models\User;

class GroupePolicy
{
    /**
     * Détermine si l'utilisateur peut consulter le groupe.
     *
     * Accessible aux membres, à l'enseignant de la classe et aux admins.
     */
    public function view(User $user, Groupe $groupe): bool
    {
        if ($user->isAdmin() || $groupe->classe->enseignant_id === $user->id) {
            return true;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut gérer les membres du groupe.
     *
     * Réservé au créateur du groupe uniquement.
     */
    public function manageMembers(User $user, Groupe $groupe): bool
    {
        return $groupe->created_by === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut gérer les thématiques du groupe.
     *
     * Accessible à tous les membres du groupe.
     */
    public function manageThematiques(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut supprimer le groupe.
     *
     * Réservé au créateur uniquement.
     */
    public function delete(User $user, Groupe $groupe): bool
    {
        return $groupe->created_by === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut ajouter une note au groupe.
     *
     * Accessible à tous les membres du groupe.
     */
    public function addNote(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut uploader un média dans le groupe.
     *
     * Accessible à tous les membres du groupe.
     */
    public function addMedia(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut supprimer un média du groupe.
     *
     * L'auteur du média, l'enseignant de la classe et les admins peuvent supprimer.
     */
    public function deleteMedia(User $user, Groupe $groupe): bool
    {
        return $user->isAdmin() || $groupe->classe->enseignant_id === $user->id;
    }
}
