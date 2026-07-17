<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_blocs` qui stocke les blocs de contenu de chaque section
     * d'un projet musée (texte riche, image, carrousel, vidéo, séparateur).
     *
     * Le champ `contenu` (JSON) varie selon le type :
     *   - texte   : { html: "..." }
     *   - image   : { image_id: 1, legende: "...", position: "centre" }
     *   - carrousel : { image_ids: [1, 2, 3] }
     *   - video   : { source: "upload|youtube|vimeo", groupe_video_id: 1, url: "...", titre: "..." }
     *   - separateur : {}
     */
    public function up(): void
    {
        Schema::create('musee_blocs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_recherche_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->string('type', 20); // texte, image, carrousel, video, separateur
            $table->json('contenu')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['projet_recherche_id', 'section_id', 'ordre']);
        });
    }

    /**
     * Supprime la table `musee_blocs`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_blocs');
    }
};
