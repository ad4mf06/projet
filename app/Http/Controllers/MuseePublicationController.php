<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseePublication;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseePublicationController extends Controller
{
    /**
     * Bascule le statut de publication du musée virtuel d'un groupe.
     *
     * Seul l'enseignant du cours peut publier ou dépublier.
     * Crée l'enregistrement MuseePublication s'il n'existe pas encore.
     *
     * @throws HttpException
     */
    public function toggle(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        abort_unless($cours->enseignant_id === auth()->id(), 403);
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $publication = MuseePublication::firstOrCreate(
            ['projet_recherche_id' => $projet->id],
            ['est_copie_prof' => false, 'est_publie' => false],
        );

        $wasPublie = $publication->est_publie;

        $publication->update([
            'est_publie' => ! $wasPublie,
            'publie_le' => $wasPublie ? null : now(),
            'publie_par' => $wasPublie ? null : auth()->id(),
        ]);

        return back()->with('success', $wasPublie ? 'Musée retiré de la galerie.' : 'Musée publié dans la galerie.');
    }
}
