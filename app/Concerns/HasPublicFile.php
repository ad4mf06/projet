<?php

namespace App\Concerns;

trait HasPublicFile
{
    /**
     * Supprime le fichier physique associé au modèle puis supprime l'enregistrement.
     */
    public function deleteWithFile(): ?bool
    {
        $fullPath = public_path($this->file_path);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return $this->delete();
    }
}
