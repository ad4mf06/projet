<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\ClasseDocumentController;
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\GroupeController;
use App\Http\Controllers\GroupeMediaController;
use App\Http\Controllers\ThematiqueController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

// Redirection post-login selon le rôle
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return match ($user->role) {
            'admin' => redirect()->route('administration.index'),
            'enseignant' => redirect()->route('enseignant.index'),
            default => redirect()->route('classes.index'),
        };
    })->name('dashboard');
});

// ─── Admin ────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/administration', [AdministrationController::class, 'index'])
        ->name('administration.index');

    Route::post('/administration/enseignants', [AdministrationController::class, 'storeEnseignant'])
        ->name('administration.enseignants.store');

    Route::put('/administration/enseignants/{enseignant}', [AdministrationController::class, 'updateEnseignant'])
        ->name('administration.enseignants.update');

    Route::delete('/administration/enseignants/{enseignant}', [AdministrationController::class, 'destroyEnseignant'])
        ->name('administration.enseignants.destroy');
});

// ─── Enseignant (+ Admin) ──────────────────────────────────────────────────────
Route::middleware(['auth', 'role:enseignant,admin'])->group(function () {
    Route::get('/enseignant', [EnseignantController::class, 'index'])
        ->name('enseignant.index');

    // Gestion des classes
    Route::post('/classes', [ClasseController::class, 'store'])
        ->name('classes.store');

    Route::put('/classes/{classe}', [ClasseController::class, 'update'])
        ->name('classes.update');

    Route::delete('/classes/{classe}', [ClasseController::class, 'destroy'])
        ->name('classes.destroy');

    Route::get('/classes/{classe}', [ClasseController::class, 'show'])
        ->name('classes.show');

    // Gestion des étudiants dans une classe
    Route::post('/classes/{classe}/etudiants', [ClasseController::class, 'storeEtudiant'])
        ->name('classes.etudiants.store');

    Route::put('/classes/{classe}/etudiants/{etudiant}', [ClasseController::class, 'updateEtudiant'])
        ->name('classes.etudiants.update');

    Route::delete('/classes/{classe}/etudiants/{etudiant}', [ClasseController::class, 'destroyEtudiant'])
        ->name('classes.etudiants.destroy');

    Route::post('/classes/{classe}/import', [ClasseController::class, 'importEtudiants'])
        ->name('classes.etudiants.import');

    // Documents de classe
    Route::post('/classes/{classe}/documents', [ClasseDocumentController::class, 'store'])
        ->name('classes.documents.store');

    Route::delete('/classes/{classe}/documents/{document}', [ClasseDocumentController::class, 'destroy'])
        ->name('classes.documents.destroy');

    // Gestion des thématiques
    Route::post('/thematiques', [ThematiqueController::class, 'store'])
        ->name('thematiques.store');

    Route::put('/thematiques/{thematique}', [ThematiqueController::class, 'update'])
        ->name('thematiques.update');

    Route::delete('/thematiques/{thematique}', [ThematiqueController::class, 'destroy'])
        ->name('thematiques.destroy');
});

// ─── Étudiant ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->group(function () {
    Route::get('/classes', [ClasseController::class, 'index'])
        ->name('classes.index');

    Route::get('/etudiant', [EtudiantController::class, 'index'])
        ->name('etudiant.index');

    // Groupes
    Route::get('/classes/{classe}/groupes', [GroupeController::class, 'index'])
        ->name('groupes.index');

    Route::post('/classes/{classe}/groupes', [GroupeController::class, 'store'])
        ->name('groupes.store');

    Route::delete('/classes/{classe}/groupes/{groupe}', [GroupeController::class, 'destroy'])
        ->name('groupes.destroy');

    Route::post('/groupes/{groupe}/notes', [GroupeController::class, 'storeNote'])
        ->name('groupes.notes.store');

    Route::delete('/groupes/{groupe}/notes/{note}', [GroupeController::class, 'destroyNote'])
        ->name('groupes.notes.destroy');
});

// ─── Actions créateur du groupe ───────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->group(function () {
    Route::put('/classes/{classe}/groupes/{groupe}/thematiques', [GroupeController::class, 'updateThematiques'])
        ->name('groupes.thematiques.update');

    Route::put('/classes/{classe}/groupes/{groupe}/membres', [GroupeController::class, 'updateMembres'])
        ->name('groupes.membres.update');
});

// ─── Groupes (étudiant + enseignant + admin) ──────────────────────────────────
Route::middleware(['auth', 'role:etudiant,enseignant,admin'])->group(function () {
    Route::get('/classes/{classe}/groupes/{groupe}', [GroupeController::class, 'show'])
        ->name('groupes.show');

    // Médias du groupe
    Route::post('/classes/{classe}/groupes/{groupe}/medias', [GroupeMediaController::class, 'store'])
        ->name('groupes.medias.store');

    Route::delete('/classes/{classe}/groupes/{groupe}/medias/{media}', [GroupeMediaController::class, 'destroy'])
        ->name('groupes.medias.destroy');
});

require __DIR__.'/settings.php';
