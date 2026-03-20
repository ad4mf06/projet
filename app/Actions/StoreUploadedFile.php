<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoreUploadedFile
{
    /**
     * Déplace un fichier uploadé dans le dossier public cible et retourne ses métadonnées.
     *
     * @param  UploadedFile  $file  Le fichier provenant de la requête.
     * @param  string  $directory  Chemin relatif sous public/ (ex: "images/groupes/3").
     * @return array{nom_original: string, file_path: string, taille: int}
     */
    public function execute(UploadedFile $file, string $directory): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $fullDir = public_path($directory);

        if (! is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filename = Str::uuid().'.'.$ext;

        // Capturer la taille AVANT move() : après déplacement, le fichier temp
        // n'existe plus et getSize() lèverait SplFileInfo::getSize(): stat failed.
        $taille = $file->getSize();
        $nomOriginal = $file->getClientOriginalName();

        $file->move($fullDir, $filename);

        return [
            'nom_original' => $nomOriginal,
            'file_path' => "{$directory}/{$filename}",
            'taille' => $taille,
        ];
    }
}
