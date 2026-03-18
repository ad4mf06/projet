<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'fichier' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('fichier');
        $ext  = strtolower($file->getClientOriginalExtension());

        $photoExtensions    = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $documentExtensions = ['pdf', 'doc', 'docx'];

        if (! in_array($ext, array_merge($photoExtensions, $documentExtensions))) {
            return back()->withErrors(['fichier' => 'Format non supporté. Utilisez JPG, PNG, WEBP, PDF ou DOCX.']);
        }

        $type         = in_array($ext, $photoExtensions) ? 'photo' : 'document';
        $directory    = public_path("images/groupes/{$groupe->id}");
        $nomOriginal  = $file->getClientOriginalName();
        $taille       = $file->getSize();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::uuid() . '.' . $ext;
        $file->move($directory, $filename);

        $groupe->medias()->create([
            'user_id'      => $user->id,
            'nom_original' => $nomOriginal,
            'file_path'    => "images/groupes/{$groupe->id}/{$filename}",
            'type'         => $type,
            'taille'       => $taille,
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

        $fullPath = public_path($media->file_path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $media->delete();

        return back()->with('success', 'Fichier supprimé.');
    }
}
