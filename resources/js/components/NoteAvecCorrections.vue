<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Highlight from '@tiptap/extension-highlight';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { MessageSquare, X } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { CommentMark } from '@/extensions/CommentMark';
import * as correctionsRoutes from '@/routes/groupes/notes/corrections';

type Correction = {
    id: number;
    commentaire_id: string;
    contenu: string;
    user_id: number;
};

type Note = {
    id: number;
    contenu: string;
    corrections: Correction[];
};

const props = defineProps<{
    note: Note;
    estEnseignant: boolean;
    groupeId: number;
}>();

// ─── Normalisation HTML ────────────────────────────────────────────────────────

/** Convertit le texte brut en paragraphes HTML pour TipTap. */
function normalizeHtml(content: string): string {
    if (!content) {
        return '<p></p>';
    }

    if (!/<[a-z][\s\S]*>/i.test(content)) {
        return content
            .split(/\n{2,}/)
            .map((p) => `<p>${p.replace(/\n/g, '<br>')}</p>`)
            .join('');
    }

    return content;
}

// ─── Éditeur TipTap ────────────────────────────────────────────────────────────

const editorWrapRef = ref<HTMLDivElement | null>(null);

const editor = useEditor({
    content: normalizeHtml(props.note.contenu),
    editable: false,
    extensions: [StarterKit, Highlight.configure({ multicolor: true }), CommentMark],
    editorProps: {
        attributes: { class: 'prose prose-sm max-w-none px-3 py-2 min-h-[60px]' },
    },
});

watch(
    () => props.note.contenu,
    (val) => {
        const html = normalizeHtml(val);

        if (editor.value && editor.value.getHTML() !== html) {
            editor.value.commands.setContent(html, false);
        }
    },
);

onBeforeUnmount(() => editor.value?.destroy());

// ─── Mode annotation (enseignant) ─────────────────────────────────────────────

/** Génère un UUID v4, avec fallback pour les contextes non-sécurisés (HTTP). */
function generateUUID(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (+c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (+c / 4)))).toString(16),
    );
}

const annotationModeActive = ref(false);
const showBubble = ref(false);
const brouillon = ref('');
const isSaving = ref(false);
const savedRange = ref<Range | null>(null);

function toggleAnnotationMode() {
    annotationModeActive.value = !annotationModeActive.value;

    if (!annotationModeActive.value) {
        showBubble.value = false;
        brouillon.value = '';
        savedRange.value = null;
    }
}

/** Capture la sélection et signale qu'un texte est prêt à être annoté. */
function handleMouseUp() {
    if (!props.estEnseignant || !annotationModeActive.value) {
        return;
    }

    // Ne pas interférer si le formulaire d'annotation est déjà ouvert.
    if (showBubble.value) {
        return;
    }

    const selection = window.getSelection();

    if (!selection || selection.isCollapsed || !selection.toString().trim()) {
        return;
    }

    // Capturer le Range avant que la sélection DOM soit perdue au moment du clic sur "Publier".
    savedRange.value = selection.getRangeAt(0).cloneRange();
    showBubble.value = true;
}

async function saveCorrection() {
    if (!editor.value || !brouillon.value.trim() || !savedRange.value) {
        return;
    }

    isSaving.value = true;
    const commentId = generateUUID();

    // Active temporairement l'édition pour insérer la marque.
    editor.value.setEditable(true);

    const { from, to } = editor.value.state.selection;

    if (from === to) {
        // Utilise le Range capturé à l'ouverture du formulaire (la sélection DOM est perdue au clic).
        const domRange = savedRange.value;
        const fromPos = editor.value.view.posAtDOM(domRange.startContainer, domRange.startOffset);
        const toPos = editor.value.view.posAtDOM(domRange.endContainer, domRange.endOffset);
        editor.value.chain().setTextSelection({ from: fromPos, to: toPos }).setComment(commentId).run();
    } else {
        editor.value.chain().focus().setComment(commentId).run();
    }

    editor.value.setEditable(false);

    const updatedHtml = editor.value.getHTML();

    router.put(
        correctionsRoutes.upsert({ groupe: props.groupeId, note: props.note.id }).url,
        { commentaire_id: commentId, contenu: brouillon.value.trim(), note_html: updatedHtml },
        {
            preserveScroll: true,
            onSuccess: () => {
                brouillon.value = '';
                showBubble.value = false;
                savedRange.value = null;
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}

function cancelBubble() {
    showBubble.value = false;
    brouillon.value = '';
    savedRange.value = null;
}

// ─── Suppression d'une correction ─────────────────────────────────────────────

const isDeletingId = ref<number | null>(null);
const hoveredCommentId = ref<string | null>(null);

function deleteCorrection(correction: Correction) {
    if (!editor.value) {
        return;
    }

    isDeletingId.value = correction.id;

    // Retire la marque localement avant la requête pour un retour visuel immédiat.
    editor.value.setEditable(true);
    editor.value.commands.unsetComment(correction.commentaire_id);
    editor.value.setEditable(false);

    const updatedHtml = editor.value.getHTML();

    router.delete(
        correctionsRoutes.destroy({
            groupe: props.groupeId,
            note: props.note.id,
            correction: correction.id,
        }).url,
        {
            data: { note_html: updatedHtml },
            preserveScroll: true,
            onSuccess: () => {
                // Force le contenu propre dans l'éditeur après la réponse Inertia
                // pour éviter que la marque ne réapparaisse si le HTML du serveur
                // diffère légèrement du HTML local.
                editor.value?.commands.setContent(updatedHtml, false);
            },
            onFinish: () => {
                isDeletingId.value = null;
            },
        },
    );
}

/** Applique la classe CSS active sur la marque survolée dans le panneau. */
function highlightMark(commentId: string | null) {
    hoveredCommentId.value = commentId;

    if (!editorWrapRef.value) {
        return;
    }

    editorWrapRef.value.querySelectorAll('mark.comment-mark').forEach((el) => {
        el.classList.remove('comment-mark--active');
    });

    if (commentId) {
        editorWrapRef.value
            .querySelectorAll(`mark[data-comment-id="${commentId}"]`)
            .forEach((el) => el.classList.add('comment-mark--active'));
    }
}
</script>

<template>
    <div class="flex gap-4">
        <!-- ─── Éditeur TipTap ──────────────────────────────────────────────────── -->
        <div ref="editorWrapRef" class="note-editor-wrap min-w-0 flex-1" @mouseup="handleMouseUp">
            <EditorContent :editor="editor" />
        </div>

        <!-- ─── Panneau latéral ──────────────────────────────────────────────────── -->
        <div
            v-if="note.corrections.length > 0 || estEnseignant"
            class="flex w-48 shrink-0 flex-col gap-2"
        >
            <!-- Bouton mode annotation -->
            <Button
                v-if="estEnseignant"
                size="sm"
                :variant="annotationModeActive ? 'default' : 'outline'"
                class="w-full text-xs"
                @click="toggleAnnotationMode"
            >
                <MessageSquare class="mr-1.5 h-3.5 w-3.5" />
                {{ annotationModeActive ? 'Terminer' : 'Annoter' }}
            </Button>

            <!-- Formulaire d'annotation (texte sélectionné en mode annotation) -->
            <div
                v-if="showBubble"
                class="space-y-1.5 rounded-md border border-amber-200 bg-amber-50 p-2 dark:border-amber-700 dark:bg-amber-950"
            >
                <p class="text-xs text-amber-600 dark:text-amber-400">
                    Correction pour le texte sélectionné
                </p>
                <Textarea
                    v-model="brouillon"
                    placeholder="Écrire une correction…"
                    class="min-h-[60px] text-sm"
                    rows="2"
                    autofocus
                />
                <div class="flex gap-1.5">
                    <Button size="sm" :disabled="isSaving || !brouillon.trim()" @click="saveCorrection">
                        Publier
                    </Button>
                    <Button size="sm" variant="ghost" @click="cancelBubble">
                        Annuler
                    </Button>
                </div>
            </div>

            <!-- Liste des corrections -->
            <div
                v-for="correction in note.corrections"
                :key="correction.id"
                class="correction-card group relative rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
                @mouseenter="highlightMark(correction.commentaire_id)"
                @mouseleave="highlightMark(null)"
            >
                <MessageSquare class="mb-1 h-3 w-3 text-amber-500" />
                <p>{{ correction.contenu }}</p>
                <button
                    v-if="estEnseignant"
                    type="button"
                    class="absolute right-1 top-1 hidden text-amber-400 hover:text-amber-700 group-hover:block"
                    :disabled="isDeletingId === correction.id"
                    @click="deleteCorrection(correction)"
                >
                    <X class="h-3 w-3" />
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Marques de correction ──────────────────────────────────────────────────── */
:deep(mark.comment-mark) {
    background: transparent;
    border-bottom: 2px solid #f97316;
    border-radius: 0;
    cursor: pointer;
    padding-bottom: 1px;
}

:deep(mark.comment-mark--active) {
    background: #bfdbfe;
    border-bottom-color: #3b82f6;
}

/* ─── Mode annotation actif ─────────────────────────────────────────────────── */
.note-editor-wrap :deep(.ProseMirror) {
    user-select: text;
}
</style>
