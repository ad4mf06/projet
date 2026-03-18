<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class EtudiantController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('classes.index');
    }
}
