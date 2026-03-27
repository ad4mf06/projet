<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Affiche la page des paramètres de sécurité.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Security');
    }

    /**
     * Met à jour le mot de passe de l'utilisateur.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back();
    }
}
