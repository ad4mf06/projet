<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClasseController extends Controller
{
    // Vue étudiant : liste des classes dans lesquelles il est inscrit
    public function index(): Response
    {
        $classes = auth()->user()
            ->classesInscrites()
            ->with('enseignant:id,prenom,nom')
            ->get();

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
        ]);
    }

    // Vue enseignant : détail d'une classe avec gestion des étudiants, groupes et documents
    public function show(Classe $classe): Response
    {
        $this->authorizeClasse($classe);

        $etudiants = $classe->etudiants()
            ->orderBy('nom')
            ->get()
            ->map(fn ($etudiant) => [
                'id'           => $etudiant->id,
                'prenom'       => $etudiant->prenom,
                'nom'          => $etudiant->nom,
                'email'        => $etudiant->email,
                'no_da'        => $etudiant->no_da,
                'statut_cours' => $etudiant->pivot->statut_cours,
            ]);

        $groupes = $classe->groupes()
            ->with(['membres:id,prenom,nom', 'thematiques:id,nom', 'createur:id,prenom,nom'])
            ->get();

        $documents = $classe->documents()->get();

        return Inertia::render('Classes/Show', [
            'classe'    => $classe,
            'etudiants' => $etudiants,
            'groupes'   => $groupes,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nom_cours' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'heures_par_semaine' => ['required', 'numeric', 'min:0.5', 'max:60'],
            'code' => ['required', 'string', 'max:20'],
            'groupe' => ['required', 'string', 'max:20'],
        ]);

        auth()->user()->classes()->create($validated);

        return back()->with('success', 'Classe créée avec succès.');
    }

    public function update(Request $request, Classe $classe): RedirectResponse
    {
        $this->authorizeClasse($classe);

        $validated = $request->validate([
            'nom_cours' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'heures_par_semaine' => ['required', 'numeric', 'min:0.5', 'max:60'],
            'code' => ['required', 'string', 'max:20'],
            'groupe' => ['required', 'string', 'max:20'],
        ]);

        $classe->update($validated);

        return back()->with('success', 'Classe mise à jour avec succès.');
    }

    public function destroy(Classe $classe): RedirectResponse
    {
        $this->authorizeClasse($classe);

        $classe->delete();

        return to_route('enseignant.index')->with('success', 'Classe supprimée.');
    }

    // Ajouter un étudiant manuellement
    public function storeEtudiant(Request $request, Classe $classe): RedirectResponse
    {
        $this->authorizeClasse($classe);

        $validated = $request->validate([
            'prenom'      => ['required', 'string', 'max:255'],
            'nom'         => ['required', 'string', 'max:255'],
            'no_da'       => ['required', 'string', 'max:20'],
            'statut_cours' => ['nullable', 'string', 'max:100'],
            'email'       => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $etudiant = $this->findOrCreateEtudiant(
            $validated['no_da'],
            $validated['prenom'],
            $validated['nom'],
            $validated['email'] ?? null,
        );

        if (! $classe->etudiants()->where('user_id', $etudiant->id)->exists()) {
            $classe->etudiants()->attach($etudiant->id, [
                'statut_cours' => $validated['statut_cours'] ?? null,
            ]);
        }

        return back()->with('success', 'Étudiant ajouté avec succès.');
    }

    // Modifier un étudiant dans la classe
    public function updateEtudiant(Request $request, Classe $classe, User $etudiant): RedirectResponse
    {
        $this->authorizeClasse($classe);

        $validated = $request->validate([
            'prenom'      => ['required', 'string', 'max:255'],
            'nom'         => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($etudiant->id)],
            'no_da'       => ['required', 'string', 'max:20'],
            'statut_cours' => ['nullable', 'string', 'max:100'],
        ]);

        $etudiant->update([
            'prenom' => $validated['prenom'],
            'nom'    => $validated['nom'],
            'email'  => $validated['email'],
            'no_da'  => $validated['no_da'],
        ]);

        $classe->etudiants()->updateExistingPivot($etudiant->id, [
            'statut_cours' => $validated['statut_cours'] ?? null,
        ]);

        return back()->with('success', 'Étudiant mis à jour.');
    }

    // Retirer un étudiant de la classe
    public function destroyEtudiant(Classe $classe, User $etudiant): RedirectResponse
    {
        $this->authorizeClasse($classe);

        $classe->etudiants()->detach($etudiant->id);

        return back()->with('success', 'Étudiant retiré de la classe.');
    }

    // Importer des étudiants via CSV
    public function importEtudiants(Request $request, Classe $classe): RedirectResponse
    {
        $this->authorizeClasse($classe);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv');

        // Lire le contenu brut et convertir en UTF-8 si nécessaire (Excel → Windows-1252)
        $content = file_get_contents($file->getPathname());
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Écrire le contenu converti dans un fichier temporaire
        $tmp = tmpfile();
        fwrite($tmp, $content);
        rewind($tmp);
        $handle = $tmp;

        // Ignorer l'entête
        fgetcsv($handle, 0, ';');

        $created = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            [$noDa, $nom, $prenom, $statut] = $row;

            // Retirer le BOM UTF-8 éventuel sur le premier champ
            $noDa   = ltrim(trim($noDa), "\xEF\xBB\xBF");
            $nom    = trim($nom);
            $prenom = trim($prenom);
            $statut = trim($statut);

            if (empty($noDa) || empty($nom) || empty($prenom)) {
                continue;
            }

            $etudiant = $this->findOrCreateEtudiant($noDa, $prenom, $nom);

            if (! $classe->etudiants()->where('user_id', $etudiant->id)->exists()) {
                $classe->etudiants()->attach($etudiant->id, [
                    'statut_cours' => $statut ?: null,
                ]);
                $created++;
            }
        }

        fclose($handle);

        return back()->with('success', "{$created} étudiant(s) importé(s) avec succès.");
    }

    private function findOrCreateEtudiant(
        string $noDa,
        string $prenom,
        string $nom,
        ?string $email = null,
    ): User {
        // Si ce numéro de DA existe déjà dans users, retourner l'étudiant existant
        $existant = User::where('no_da', $noDa)->where('role', 'etudiant')->first();
        if ($existant) {
            return $existant;
        }

        // Sinon créer (ou retrouver) l'étudiant par courriel
        $email ??= $this->generateEmail($prenom, $nom);

        return User::firstOrCreate(
            ['email' => strtolower($email)],
            [
                'prenom'             => $prenom,
                'nom'                => $nom,
                'no_da'              => $noDa,
                'password'           => 'password',
                'role'               => 'etudiant',
                'email_verified_at'  => now(),
            ]
        );
    }

    private function generateEmail(string $prenom, string $nom): string
    {
        $prenom = Str::lower(Str::ascii($prenom));
        $nom    = Str::lower(Str::ascii($nom));

        return "{$prenom}.{$nom}@etu.cegepdrummond.ca";
    }

    private function authorizeClasse(Classe $classe): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($classe->enseignant_id !== $user->id) {
            abort(403, 'Vous ne pouvez pas modifier cette classe.');
        }
    }
}
