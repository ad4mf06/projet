<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_templates` qui stocke la configuration visuelle
     * (polices, couleurs, palette) définie par l'enseignant pour un TypeProjet musée.
     */
    public function up(): void
    {
        Schema::create('musee_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_projet_id')->unique()->constrained('types_projets')->cascadeOnDelete();

            // Polices — noms de Google Fonts ou valeurs CSS
            $table->string('font_titre_page', 100)->default('Georgia');
            $table->string('font_sous_titre', 100)->default('Georgia');
            $table->string('font_titre_section', 100)->default('Georgia');
            $table->string('font_corps', 100)->default('Arial');
            $table->string('font_legende', 100)->default('Arial');

            // Couleurs — valeurs hexadécimales
            $table->string('couleur_fond', 20)->default('#F5EFE0');
            $table->string('couleur_titre', 20)->default('#1A2744');
            $table->string('couleur_corps', 20)->default('#2C2C2C');
            $table->string('couleur_accent', 20)->default('#C9A040');
            $table->string('couleur_lien_externe', 20)->default('#E07010');

            // Palette prédéfinie sélectionnée (null = personnalisée)
            $table->string('palette', 50)->nullable();

            // Thème global : 'clair' ou 'sombre'
            $table->string('theme', 10)->default('clair');

            $table->timestamps();
        });
    }

    /**
     * Supprime la table `musee_templates`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_templates');
    }
};
