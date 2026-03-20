<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupeMediaController extends Controller
{
    /**
     * Uploade un média et l'associe au groupe.
     *
     * La validation MIME réelle (pas uniquement l'extension client) est assurée par Laravel.
     * Seuls les membres du groupe peuvent uploader.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $this->authorize('addMedia', $groupe);

        $request->validate([
            'fichier' => [
                'required',
                'file',
                'max:51200',
                // mimes: valide le contenu réel du fichier, pas seulement l'extension déclarée par le client
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,mp3,wav,ogg,m4a,aac',
            ],
        ]);

        $file = $request->file('fichier');
        $ext = strtolower($file->getClientOriginalExtension());

        $type = match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) => 'photo',
            in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac']) => 'audio',
            default => 'document',
        };

        $meta = (new StoreUploadedFile)->execute(
            $file,
            "images/groupes/{$groupe->id}"
        );

        $groupe->medias()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            ...$meta,
        ]);

        return back()->with('success', __('media.added'));
    }

    /**
     * Supprime un média du groupe et son fichier physique.
     *
     * L'auteur du média, l'enseignant de la classe et les admins peuvent supprimer.
     *
     * @throws AuthorizationException
     */
    public function destroy(Classe $classe, Groupe $groupe, GroupeMedia $media): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $user = auth()->user();

        // L'auteur peut supprimer son propre média — les autres cas passent par la Policy
        if ($media->user_id !== $user->id) {
            $this->authorize('deleteMedia', $groupe);
        }

        $media->deleteWithFile();

        return back()->with('success', __('media.deleted'));
    }
}
