<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\ClasseDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $ext  = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['pdf', 'doc', 'docx'])) {
            return back()->withErrors(['document' => 'Format non supporté. Utilisez PDF ou DOCX uniquement.']);
        }

        $nomOriginal = $file->getClientOriginalName();
        $taille      = $file->getSize();
        $directory   = public_path("images/classes/{$classe->id}");

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::uuid() . '.' . $ext;
        $file->move($directory, $filename);

        $classe->documents()->create([
            'enseignant_id' => $user->id,
            'nom_original'  => $nomOriginal,
            'file_path'     => "images/classes/{$classe->id}/{$filename}",
            'type'          => $ext,
            'taille'        => $taille,
        ]);

        return back()->with('success', 'Document ajouté avec succès.');
    }

    public function destroy(Classe $classe, ClasseDocument $document): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isAdmin() && $classe->enseignant_id !== $user->id) {
            abort(403);
        }

        $fullPath = public_path($document->file_path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }
}
