<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\ClasseDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClasseDocumentController extends Controller
{
    /**
     * Uploade un document et l'associe à la classe.
     *
     * La validation MIME réelle (pas uniquement l'extension client) est assurée par Laravel.
     * L'autorisation délègue à ClassePolicy::update().
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $this->authorize('update', $classe);

        $request->validate([
            // mimes: valide le contenu réel du fichier, pas seulement l'extension déclarée par le client
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
        ]);

        $meta = (new StoreUploadedFile)->execute(
            $request->file('document'),
            "images/classes/{$classe->id}"
        );

        $classe->documents()->create([
            'enseignant_id' => auth()->id(),
            'type' => strtolower($request->file('document')->getClientOriginalExtension()),
            ...$meta,
        ]);

        return back()->with('success', __('document.added'));
    }

    /**
     * Supprime un document de la classe et son fichier physique.
     *
     * @throws AuthorizationException
     */
    public function destroy(Classe $classe, ClasseDocument $document): RedirectResponse
    {
        $this->authorize('update', $classe);

        $document->deleteWithFile();

        return back()->with('success', __('document.deleted'));
    }
}
