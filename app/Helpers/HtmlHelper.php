<?php

namespace App\Helpers;

class HtmlHelper
{
    /**
     * Retire les marques d'annotation TipTap (CommentMark) d'un HTML en gardant le texte brut.
     *
     * Les balises ciblées ont la forme :
     *   <mark data-comment-id="UUID" data-annotation-type="commentaire" ...>mot</mark>
     */
    public static function stripAnnotationMarks(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // On retire uniquement les <mark> portant l'attribut data-comment-id (marques TipTap).
        // Les éventuels <mark> de surlignage génériques (Highlight) ne sont pas touchés.
        return preg_replace(
            '/<mark\b[^>]*\bdata-comment-id\b[^>]*>(.*?)<\/mark>/is',
            '$1',
            $html,
        ) ?? $html;
    }
}
