import { Extension } from '@tiptap/core';
import type { Editor } from '@tiptap/core';

declare global {
    interface Window {
        /** Activé par l'extension navigateur Antidote JS-Connect */
        activeAntidoteAPI_JSConnect?: (callback?: () => void) => void;
    }
}

const GROUPE_ATTR = 'data-antidoteapi_jsconnect_groupe_id';

/** Génère un identifiant de groupe JS-Connect unique pour une instance d'éditeur. */
export function generateAntidoteGroupeId(): string {
    return `antidote-editor-${Date.now()}-${Math.floor(Math.random() * 10000)}`;
}

interface AntidoteOptions {
    /** Identifiant de groupe JS-Connect unique pour cette instance d'éditeur. */
    groupeId: string;
}

/**
 * Lit le HTML courant du DOM ProseMirror (modifié par Antidote) et
 * le réinjecte dans l'état TipTap pour resynchroniser le modèle.
 */
function syncFromDom(editor: Editor): void {
    const dom = editor.view.dom as HTMLElement;
    editor.commands.setContent(dom.innerHTML, { emitUpdate: false });
}

/**
 * Extension TipTap qui connecte l'éditeur au correcteur Antidote via l'API JS-Connect.
 *
 * Fonctionnement :
 * 1. L'identifiant de groupe fourni est attaché au DOM contenteditable de l'éditeur.
 * 2. L'API JS-Connect est activée avec un callback qui resynchronise l'état TipTap
 *    après chaque correction effectuée par Antidote.
 * 3. Si l'extension navigateur Antidote n'est pas installée, tout est ignoré silencieusement.
 */
export const AntidoteExtension = Extension.create<AntidoteOptions>({
    name: 'antidote',

    addOptions() {
        return {
            groupeId: generateAntidoteGroupeId(),
        };
    },

    onCreate() {
        const editor = this.editor;
        const dom = editor.view.dom as HTMLElement;

        dom.setAttribute(GROUPE_ATTR, this.options.groupeId);

        // Active l'API JS-Connect avec le callback de resynchronisation.
        // Le script est chargé avec defer, donc on attend que la page soit complète.
        const activer = () => {
            if (typeof window.activeAntidoteAPI_JSConnect === 'function') {
                window.activeAntidoteAPI_JSConnect(() => syncFromDom(editor));
            }
        };

        if (document.readyState === 'complete') {
            activer();
        } else {
            window.addEventListener('load', activer, { once: true });
        }
    },
});
