<?php

namespace App\Actions;

use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProjetWord
{
    /**
     * Génère et retourne le projet de groupe en .docx.
     * Chaque étudiant a sa propre section de conclusion.
     */
    public function execute(ProjetRecherche $projet, Groupe $groupe): StreamedResponse
    {
        $classe = $groupe->classe;
        $enseignant = $classe->enseignant;

        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);

        // ─── Page titre ───────────────────────────────────────────────────────
        $pageTitre = $word->addSection();

        // Chaque membre sur sa propre ligne
        foreach ($groupe->membres as $membre) {
            $this->addCenteredText($pageTitre, "{$membre->prenom} {$membre->nom}");
        }

        $this->addCenteredText($pageTitre, $classe->nom_cours);
        $this->addCenteredText($pageTitre, "{$classe->code} / Gr. {$classe->groupe}", 10);
        $pageTitre->addTextBreak(3);
        $this->addCenteredText($pageTitre, strtoupper($projet->titre_projet ?? 'Recherche documentaire'), 16, true);
        $this->addCenteredText($pageTitre, 'RECHERCHE DOCUMENTAIRE');
        $pageTitre->addTextBreak(3);
        $this->addCenteredText($pageTitre, 'Travail présenté à');
        $this->addCenteredText($pageTitre, "{$enseignant->prenom} {$enseignant->nom}", 12, true);
        $pageTitre->addTextBreak(2);
        $this->addCenteredText($pageTitre, 'Département des sciences humaines', 10);
        $this->addCenteredText($pageTitre, 'Cégep de Drummondville', 10);
        $this->addCenteredText($pageTitre, 'Le '.now()->translatedFormat('j F Y'), 10);

        // ─── Table des matières ───────────────────────────────────────────────
        $tocSection = $word->addSection();
        $tocSection->addText('TABLE DES MATIÈRES', ['bold' => true, 'size' => 13, 'allCaps' => true], ['alignment' => 'center']);
        $tocSection->addTextBreak(1);
        $tocSection->addText('Introduction');

        for ($i = 1; $i <= 5; $i++) {
            $titre = $projet->{"dev_{$i}_titre"} ?: "Paragraphe de développement {$i}";
            $tocSection->addText("{$i}. {$titre}");
        }

        // Autant d'entrées de conclusion que de membres
        foreach ($groupe->membres as $membre) {
            $tocSection->addText("Conclusion — {$membre->prenom} {$membre->nom}");
        }

        // ─── Introduction ─────────────────────────────────────────────────────
        $introSection = $word->addSection();
        $introSection->addText('INTRODUCTION', ['bold' => true, 'size' => 13, 'allCaps' => true]);
        $introSection->addTextBreak(1);

        $this->addHtmlContent($introSection, $projet->introduction_amener, 'Amener');
        $this->addHtmlContent($introSection, $projet->introduction_poser, 'Poser');
        $this->addHtmlContent($introSection, $projet->introduction_diviser, 'Diviser');

        // ─── 5 paragraphes de développement ──────────────────────────────────
        for ($i = 1; $i <= 5; $i++) {
            $devSection = $word->addSection();
            $titreDev = strtoupper($projet->{"dev_{$i}_titre"} ?: "Paragraphe de développement {$i}");
            $devSection->addText($titreDev, ['bold' => true, 'size' => 13, 'allCaps' => true]);
            $devSection->addTextBreak(1);
            $this->addHtmlContent($devSection, $projet->{"dev_{$i}_contenu"});
        }

        // ─── Conclusions individuelles (une par membre) ───────────────────────
        foreach ($groupe->membres as $membre) {
            /** @var ProjetConclusion|null $conclusion */
            $conclusion = $projet->conclusions
                ->firstWhere('user_id', $membre->id);

            $conclusionSection = $word->addSection();
            $conclusionSection->addText(
                "CONCLUSION — {$membre->prenom} {$membre->nom}",
                ['bold' => true, 'size' => 13, 'allCaps' => true],
            );
            $conclusionSection->addTextBreak(1);
            $this->addHtmlContent($conclusionSection, $conclusion?->contenu);
        }

        // ─── Stream du fichier ────────────────────────────────────────────────
        $nomFichier = sprintf('projet_groupe_%d.docx', $groupe->numero);

        return response()->streamDownload(function () use ($word) {
            $writer = IOFactory::createWriter($word, 'Word2007');
            $writer->save('php://output');
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Ajoute un paragraphe centré dans une section Word.
     */
    private function addCenteredText(Section $section, string $text, int $size = 12, bool $bold = false): void
    {
        $section->addText(
            htmlspecialchars($text),
            ['size' => $size, 'bold' => $bold],
            ['alignment' => 'center'],
        );
    }

    /**
     * Ajoute du contenu HTML (issu de TipTap) dans une section Word.
     * Utilise le parser HTML de PhpWord avec fallback sur texte brut.
     */
    private function addHtmlContent(Section $section, ?string $html, ?string $label = null): void
    {
        if ($label) {
            $section->addText(
                strtoupper($label),
                ['bold' => false, 'size' => 9, 'color' => '888888'],
            );
        }

        if (empty($html) || trim(strip_tags($html)) === '') {
            $section->addText('(Section non rédigée)', ['italic' => true, 'color' => '999999']);
            $section->addTextBreak(1);

            return;
        }

        try {
            Html::addHtml($section, $html, false, false);
        } catch (\Throwable) {
            // Fallback : texte brut si le parser HTML échoue
            $section->addText(htmlspecialchars(strip_tags($html)));
        }

        $section->addTextBreak(1);
    }
}
