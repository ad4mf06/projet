<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\ClasseDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClasseDocumentController extends Controller
{
    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isAdmin() && $classe->enseignant_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('document');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['pdf', 'doc', 'docx'])) {
            return back()->withErrors(['document' => 'Format non supporté. Utilisez PDF ou DOCX uniquement.']);
        }

        $meta = (new StoreUploadedFile)->execute(
            $file,
            "images/classes/{$classe->id}"
        );

        $classe->documents()->create([
            'enseignant_id' => $user->id,
            'type' => $ext,
            ...$meta,
        ]);

        return back()->with('success', 'Document ajouté avec succès.');
    }

    public function destroy(Classe $classe, ClasseDocument $document): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isAdmin() && $classe->enseignant_id !== $user->id) {
            abort(403);
        }

        $document->deleteWithFile();

        return back()->with('success', 'Document supprimé.');
    }
}
