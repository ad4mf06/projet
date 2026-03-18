<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupeMediaController extends Controller
{
    public function store(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        if ($groupe->classe_id !== $classe->id) {
            abort(404);
        }

        $user = auth()->user();

        if (! $groupe->membres()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        $request->validate([
            'fichier' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('fichier');
        $ext = strtolower($file->getClientOriginalExtension());

        $photoExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $documentExtensions = ['pdf', 'doc', 'docx'];
        $audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];

        $allowedExtensions = array_merge($photoExtensions, $documentExtensions, $audioExtensions);

        if (! in_array($ext, $allowedExtensions)) {
            return back()->withErrors(['fichier' => 'Format non supporté. Utilisez JPG, PNG, WEBP, PDF, DOCX, MP3, WAV, OGG, M4A ou AAC.']);
        }

        $type = match (true) {
            in_array($ext, $photoExtensions) => 'photo',
            in_array($ext, $audioExtensions) => 'audio',
            default => 'document',
        };

        $meta = (new StoreUploadedFile)->execute(
            $file,
            "images/groupes/{$groupe->id}"
        );

        $groupe->medias()->create([
            'user_id' => $user->id,
            'type' => $type,
            ...$meta,
        ]);

        return back()->with('success', 'Fichier ajouté avec succès.');
    }

    public function destroy(Classe $classe, Groupe $groupe, GroupeMedia $media): RedirectResponse
    {
        if ($groupe->classe_id !== $classe->id) {
            abort(404);
        }

        $user = auth()->user();

        $peutSupprimer = $media->user_id === $user->id
            || $user->isAdmin()
            || $groupe->classe->enseignant_id === $user->id;

        if (! $peutSupprimer) {
            abort(403);
        }

        $media->deleteWithFile();

        return back()->with('success', 'Fichier supprimé.');
    }
}
