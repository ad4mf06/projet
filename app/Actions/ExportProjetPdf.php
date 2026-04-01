<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ExportProjetPdf
{
    /**
     * Génère et retourne le projet de groupe en PDF.
     * Les conclusions sont individuelles (une par étudiant).
     */
    public function execute(ProjetRecherche $projet, Groupe $groupe): Response
    {
        $classe = $groupe->classe;
        $enseignant = $classe->enseignant;

        // Noms des membres pour la page titre — chacun sur sa propre ligne dans la vue
        $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();

        $pdf = Pdf::loadView('projets.export', [
            'projet' => $projet,
            'groupe' => $groupe,
            'classe' => $classe,
            'enseignant' => $enseignant,
            'membres' => $membres,
            // Les conclusions sont chargées via $projet->conclusions (relation)
            // Closure exposée à la vue Blade pour nettoyer les marques d'annotation
            'stripMarks' => fn (?string $html): string => HtmlHelper::stripAnnotationMarks($html),
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf('projet_groupe_%d.pdf', $groupe->numero);

        return $pdf->download($nomFichier);
    }
}
