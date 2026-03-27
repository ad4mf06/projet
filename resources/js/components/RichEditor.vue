<script setup lang="ts">
import CharacterCount from '@tiptap/extension-character-count';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Typography from '@tiptap/extension-typography';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold as BoldIcon,
    Highlighter,
    Italic as ItalicIcon,
    Link as LinkIcon,
    Link2Off,
    List,
    ListOrdered,
    MessageSquare,
    Minus,
    Palette,
    Pencil,
    Quote,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Underline as UnderlineIcon,
    X,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { CommentMark } from '@/extensions/CommentMark';

type Annotation = {
    id: number;
    commentaire_id: string;
    contenu: string;
    user_id: number;
};

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    maxLength?: number;
    readOnly?: boolean;
    estEnseignant?: boolean;
    corrections?: Annotation[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'save-annotation': [payload: { commentaire_id: string; contenu: string; html: string; type: string }];
    'delete-annotation': [payload: { correction: Annotation; html: string }];
}>();

// ─── Extensions ───────────────────────────────────────────────────────────────
const extensions = [
    // StarterKit v3 inclut Link et Underline par défaut — on les exclut ici
    // car ils sont ajoutés séparément avec des options personnalisées.
    StarterKit.configure({ underline: false, link: false }),
    Underline,
    Subscript,
    Superscript,
    TextStyle,
    Color,
    Highlight.configure({ multicolor: true }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Link.configure({ openOnClick: false, HTMLAttributes: { class: 'text-primary underline' } }),
    Typography,
    Placeholder.configure({ placeholder: props.placeholder ?? 'Rédigez ici…' }),
    CharacterCount.configure(props.maxLength ? { limit: props.maxLength } : {}),
    CommentMark,
];

const editorWrapRef = ref<HTMLDivElement | null>(null);

const editor = useEditor({
    content: props.modelValue || '',
    editable: !props.readOnly,
    extensions,
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none min-h-[160px] focus:outline-none px-3 py-2',
        },
    },
    onUpdate({ editor: e }) {
        emit('update:modelValue', e.getHTML());
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (editor.value && editor.value.getHTML() !== val) {
            editor.value.commands.setContent(val || '', false);
        }
    },
);

watch(
    () => props.readOnly,
    (val) => editor.value?.setEditable(!val),
);

onBeforeUnmount(() => editor.value?.destroy());

// ─── Couleurs de texte ────────────────────────────────────────────────────────
const textColors = [
    { label: 'Noir',   value: '#000000' },
    { label: 'Gris',   value: '#6B7280' },
    { label: 'Rouge',  value: '#DC2626' },
    { label: 'Orange', value: '#EA580C' },
    { label: 'Jaune',  value: '#CA8A04' },
    { label: 'Vert',   value: '#16A34A' },
    { label: 'Bleu',   value: '#2563EB' },
    { label: 'Violet', value: '#7C3AED' },
];

const highlightColors = [
    { label: 'Jaune',  value: '#FEF08A' },
    { label: 'Vert',   value: '#BBF7D0' },
    { label: 'Bleu',   value: '#BAE6FD' },
    { label: 'Rose',   value: '#FBCFE8' },
    { label: 'Orange', value: '#FED7AA' },
];

const showTextColorPicker = ref(false);
const showHighlightPicker = ref(false);

/**
 * Applique une couleur de texte et ferme la palette.
 */
function setTextColor(color: string): void {
    editor.value?.chain().focus().setColor(color).run();
    showTextColorPicker.value = false;
}

/**
 * Applique un surlignage et ferme la palette.
 */
function setHighlight(color: string): void {
    editor.value?.chain().focus().setHighlight({ color }).run();
    showHighlightPicker.value = false;
}

function removeTextColor(): void {
    editor.value?.chain().focus().unsetColor().run();
    showTextColorPicker.value = false;
}

function removeHighlight(): void {
    editor.value?.chain().focus().unsetHighlight().run();
    showHighlightPicker.value = false;
}

// ─── Lien ─────────────────────────────────────────────────────────────────────
function toggleLink(): void {
    if (editor.value?.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();
    } else {
        const url = window.prompt('URL du lien :');

        if (url) {
            editor.value
                ?.chain()
                .focus()
                .setLink({ href: url.startsWith('http') ? url : `https://${url}` })
                .run();
        }
    }
}

// ─── Titres ───────────────────────────────────────────────────────────────────
type HeadingLevel = 1 | 2 | 3;

function getActiveHeading(): string {
    if (!editor.value) {
 return 'p';
}

    if (editor.value.isActive('heading', { level: 1 })) {
 return '1';
}

    if (editor.value.isActive('heading', { level: 2 })) {
 return '2';
}

    if (editor.value.isActive('heading', { level: 3 })) {
 return '3';
}

    return 'p';
}

function setHeading(val: string): void {
    if (!editor.value) {
 return;
}

    if (val === 'p') {
        editor.value.chain().focus().setParagraph().run();
    } else {
        editor.value
            .chain()
            .focus()
            .setHeading({ level: Number(val) as HeadingLevel })
            .run();
    }
}

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

const showBubble = ref(false);
const brouillon = ref('');
const savedRange = ref<Range | null>(null);
const isDeletingId = ref<number | null>(null);
const hoveredCommentId = ref<string | null>(null);
const editingId = ref<number | null>(null);
const editingContent = ref('');
const annotationType = ref<'commentaire' | 'correction'>('commentaire');

/**
 * Positionne et affiche la bulle de correction quand du texte est sélectionné.
 */
function handleMouseUp(): void {
    if (!props.estEnseignant) {
        return;
    }

    // Ne pas interférer si la bulle ou le mode édition est déjà actif.
    if (showBubble.value || editingId.value !== null) {
        return;
    }

    const selection = window.getSelection();

    if (!selection || selection.isCollapsed || !selection.toString().trim()) {
        return;
    }

    // Capturer le Range avant que la sélection DOM soit perdue au clic sur "Publier".
    savedRange.value = selection.getRangeAt(0).cloneRange();
    showBubble.value = true;
}

/**
 * Insère la marque CommentMark sur la sélection et émet l'annotation pour persistance.
 */
function saveAnnotation(): void {
    if (!editor.value || !brouillon.value.trim() || !savedRange.value) {
        return;
    }

    const commentId = generateUUID();

    // Active temporairement l'édition pour insérer la marque.
    editor.value.setEditable(true);

    const { from, to } = editor.value.state.selection;

    if (from === to) {
        // La sélection TipTap n'est pas synchronisée — on utilise le Range capturé
        // à l'ouverture de la bulle car la sélection DOM est perdue au clic sur "Publier".
        const domRange = savedRange.value;
        const fromPos = editor.value.view.posAtDOM(domRange.startContainer, domRange.startOffset);
        const toPos = editor.value.view.posAtDOM(domRange.endContainer, domRange.endOffset);
        editor.value.chain().setTextSelection({ from: fromPos, to: toPos }).setComment(commentId).run();
    } else {
        editor.value.chain().focus().setComment(commentId).run();
    }

    editor.value.setEditable(false);

    emit('save-annotation', {
        commentaire_id: commentId,
        contenu: brouillon.value.trim(),
        html: editor.value.getHTML(),
        type: annotationType.value,
    });

    brouillon.value = '';
    annotationType.value = 'commentaire';
    showBubble.value = false;
    savedRange.value = null;
}

function cancelBubble(): void {
    showBubble.value = false;
    brouillon.value = '';
    annotationType.value = 'commentaire';
    savedRange.value = null;
}

/**
 * Passe une annotation existante en mode édition.
 */
function startEdit(correction: Annotation): void {
    editingId.value = correction.id;
    editingContent.value = correction.contenu;
    showBubble.value = false;
    brouillon.value = '';
}

/**
 * Annule l'édition en cours sans sauvegarder.
 */
function cancelEdit(): void {
    editingId.value = null;
    editingContent.value = '';
}

/**
 * Sauvegarde le contenu modifié d'une annotation existante via upsert (même commentaire_id).
 */
function saveEdit(correction: Annotation): void {
    if (!editor.value || !editingContent.value.trim()) {
        return;
    }
    emit('save-annotation', {
        commentaire_id: correction.commentaire_id,
        contenu: editingContent.value.trim(),
        html: editor.value.getHTML(),
        type: correction.type,
    });
    editingId.value = null;
    editingContent.value = '';
}

/**
 * Retire la marque CommentMark et émet la suppression pour persistance.
 */
function deleteAnnotation(correction: Annotation): void {
    if (!editor.value) {
 return;
}

    isDeletingId.value = correction.id;

    editor.value.setEditable(true);
    editor.value.commands.unsetComment(correction.commentaire_id);
    editor.value.setEditable(false);

    emit('delete-annotation', {
        correction,
        html: editor.value.getHTML(),
    });

    isDeletingId.value = null;
}

/**
 * Applique la classe CSS active sur la marque survolée depuis le panneau corrections.
 */
function highlightMark(commentId: string | null): void {
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
    <div class="flex items-start gap-4">
        <!-- ─── Éditeur ──────────────────────────────────────────────────────────── -->
        <div
            ref="editorWrapRef"
            class="relative min-w-0 flex-1 rounded-md border border-input bg-white dark:bg-zinc-900"
            @mouseup="handleMouseUp"
        >
            <!-- ─── Barre d'outils ──────────────────────────────────────────────── -->
            <div
                v-if="(!readOnly || estEnseignant) && editor"
                class="flex flex-wrap items-center gap-x-0.5 gap-y-1 border-b border-input px-2 py-1.5"
            >
                <!-- Groupe 1 : Mise en forme de base -->
                <template v-if="!readOnly">
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('bold') }"       title="Gras (Ctrl+B)"      @click="editor.chain().focus().toggleBold().run()"><BoldIcon class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('italic') }"     title="Italique (Ctrl+I)"  @click="editor.chain().focus().toggleItalic().run()"><ItalicIcon class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('underline') }"  title="Souligné (Ctrl+U)"  @click="editor.chain().focus().toggleUnderline().run()"><UnderlineIcon class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('strike') }"     title="Barré"              @click="editor.chain().focus().toggleStrike().run()"><Strikethrough class="h-4 w-4" /></button>

                    <div class="sep" />

                    <!-- Groupe 2 : Script -->
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('subscript') }"   title="Indice"    @click="editor.chain().focus().toggleSubscript().run()"><SubscriptIcon class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('superscript') }" title="Exposant"  @click="editor.chain().focus().toggleSuperscript().run()"><SuperscriptIcon class="h-4 w-4" /></button>

                    <div class="sep" />

                    <!-- Groupe 3 : Couleurs -->
                    <div class="relative">
                        <button
                            type="button"
                            class="tbtn"
                            title="Couleur du texte"
                            @click="showTextColorPicker = !showTextColorPicker; showHighlightPicker = false"
                        >
                            <Palette class="h-4 w-4" />
                        </button>
                        <div v-if="showTextColorPicker" class="color-popup">
                            <button
                                v-for="c in textColors"
                                :key="c.value"
                                type="button"
                                class="swatch"
                                :style="{ background: c.value }"
                                :title="c.label"
                                @click="setTextColor(c.value)"
                            />
                            <button type="button" class="reset-btn" title="Supprimer la couleur" @click="removeTextColor">✕</button>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="tbtn"
                            :class="{ active: editor.isActive('highlight') }"
                            title="Surlignage"
                            @click="showHighlightPicker = !showHighlightPicker; showTextColorPicker = false"
                        >
                            <Highlighter class="h-4 w-4" />
                        </button>
                        <div v-if="showHighlightPicker" class="color-popup">
                            <button
                                v-for="c in highlightColors"
                                :key="c.value"
                                type="button"
                                class="swatch"
                                :style="{ background: c.value }"
                                :title="c.label"
                                @click="setHighlight(c.value)"
                            />
                            <button type="button" class="reset-btn" title="Supprimer le surlignage" @click="removeHighlight">✕</button>
                        </div>
                    </div>

                    <div class="sep" />

                    <!-- Groupe 4 : Titres -->
                    <select
                        class="tbtn-select"
                        :value="getActiveHeading()"
                        title="Style du paragraphe"
                        @change="setHeading(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="p">Normal</option>
                        <option value="1">Titre 1</option>
                        <option value="2">Titre 2</option>
                        <option value="3">Titre 3</option>
                    </select>

                    <div class="sep" />

                    <!-- Groupe 5 : Alignement -->
                    <button type="button" class="tbtn" :class="{ active: editor.isActive({ textAlign: 'left' }) }"    title="Aligner à gauche"  @click="editor.chain().focus().setTextAlign('left').run()"><AlignLeft class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive({ textAlign: 'center' }) }"  title="Centrer"           @click="editor.chain().focus().setTextAlign('center').run()"><AlignCenter class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive({ textAlign: 'right' }) }"   title="Aligner à droite"  @click="editor.chain().focus().setTextAlign('right').run()"><AlignRight class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive({ textAlign: 'justify' }) }" title="Justifier"         @click="editor.chain().focus().setTextAlign('justify').run()"><AlignJustify class="h-4 w-4" /></button>

                    <div class="sep" />

                    <!-- Groupe 6 : Listes et blocs -->
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('bulletList') }"  title="Liste à puces"    @click="editor.chain().focus().toggleBulletList().run()"><List class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('orderedList') }" title="Liste numérotée"  @click="editor.chain().focus().toggleOrderedList().run()"><ListOrdered class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" :class="{ active: editor.isActive('blockquote') }"  title="Citation"         @click="editor.chain().focus().toggleBlockquote().run()"><Quote class="h-4 w-4" /></button>
                    <button type="button" class="tbtn" title="Ligne de séparation" @click="editor.chain().focus().setHorizontalRule().run()"><Minus class="h-4 w-4" /></button>

                    <div class="sep" />

                    <!-- Groupe 7 : Lien -->
                    <button
                        type="button"
                        class="tbtn"
                        :class="{ active: editor.isActive('link') }"
                        :title="editor.isActive('link') ? 'Supprimer le lien' : 'Insérer un lien'"
                        @click="toggleLink"
                    >
                        <component :is="editor.isActive('link') ? Link2Off : LinkIcon" class="h-4 w-4" />
                    </button>

                </template>
            </div>

            <!-- ─── Zone d'édition ──────────────────────────────────────────────── -->
            <EditorContent :editor="editor" />

            <!-- ─── Compteur de caractères ─────────────────────────────────────── -->
            <div
                v-if="maxLength && editor"
                class="border-t border-input px-3 py-1 text-right text-xs text-muted-foreground"
                :class="{ 'text-destructive': editor.storage.characterCount.characters() >= (maxLength ?? 0) }"
            >
                {{ editor.storage.characterCount.characters() }} / {{ maxLength }}
            </div>
        </div>

        <!-- ─── Panneau corrections — enseignant ──────────────────────────────── -->
        <div
            v-if="estEnseignant && (showBubble || editingId !== null || (corrections?.length ?? 0) > 0)"
            class="flex w-72 shrink-0 flex-col rounded-md border border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-950"
        >
            <!-- En-tête du panneau -->
            <div class="flex items-center justify-between border-b border-amber-200 px-3 py-2 dark:border-amber-700">
                <span class="flex items-center gap-1.5 text-sm font-medium text-amber-800 dark:text-amber-200">
                    <MessageSquare class="h-4 w-4" />
                    Corrections
                </span>
                <button
                    v-if="showBubble || editingId !== null"
                    type="button"
                    class="text-amber-400 hover:text-amber-700 dark:hover:text-amber-300"
                    title="Fermer"
                    @click="showBubble ? cancelBubble() : cancelEdit()"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Corps : formulaire ou liste des corrections -->
            <div class="flex-1 space-y-2 overflow-y-auto p-2">
                <!-- Formulaire d'annotation (texte sélectionné) -->
                <div
                    v-if="showBubble"
                    class="space-y-1.5 rounded-md border border-amber-300 bg-white p-2 dark:border-amber-600 dark:bg-amber-900"
                >
                    <div class="flex gap-3 text-xs text-amber-700 dark:text-amber-300">
                        <label class="flex cursor-pointer items-center gap-1">
                            <input v-model="annotationType" type="radio" value="commentaire" />
                            Commentaire
                        </label>
                        <label class="flex cursor-pointer items-center gap-1">
                            <input v-model="annotationType" type="radio" value="correction" />
                            Correction
                        </label>
                    </div>
                    <Textarea
                        v-model="brouillon"
                        placeholder="Écrire une annotation…"
                        class="min-h-[60px] text-sm"
                        rows="2"
                        autofocus
                    />
                    <div class="flex gap-1.5">
                        <Button size="sm" :disabled="!brouillon.trim()" @click="saveAnnotation">
                            Publier
                        </Button>
                        <Button size="sm" variant="ghost" @click="cancelBubble">
                            Annuler
                        </Button>
                    </div>
                </div>

                <p v-else-if="!(corrections?.length)" class="px-1 py-2 text-xs text-amber-600 dark:text-amber-400">
                    Sélectionnez du texte dans l'éditeur pour ajouter une correction.
                </p>

                <div
                    v-for="correction in corrections"
                    :key="correction.id"
                    class="correction-card group relative rounded-md border border-amber-200 bg-white px-2.5 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-900 dark:text-amber-200"
                    @mouseenter="highlightMark(correction.commentaire_id)"
                    @mouseleave="highlightMark(null)"
                >
                    <!-- Mode édition inline -->
                    <template v-if="editingId === correction.id">
                        <Textarea
                            v-model="editingContent"
                            class="min-h-[60px] text-sm"
                            rows="2"
                            autofocus
                        />
                        <div class="mt-1 flex gap-1">
                            <Button
                                size="sm"
                                :disabled="!editingContent.trim()"
                                @click="saveEdit(correction)"
                            >
                                Enregistrer
                            </Button>
                            <Button size="sm" variant="ghost" @click="cancelEdit">
                                Annuler
                            </Button>
                        </div>
                    </template>
                    <!-- Mode lecture -->
                    <template v-else>
                        <span
                            class="mb-1 inline-block rounded px-1 py-0.5 text-[10px] font-medium uppercase tracking-wide"
                            :class="correction.type === 'correction' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'"
                        >{{ correction.type }}</span>
                        <p>{{ correction.contenu }}</p>
                        <div class="absolute right-1 top-1 hidden gap-0.5 group-hover:flex">
                            <button
                                type="button"
                                class="text-amber-400 hover:text-amber-700 dark:hover:text-amber-300"
                                title="Modifier"
                                @click="startEdit(correction)"
                            >
                                <Pencil class="h-3 w-3" />
                            </button>
                            <button
                                type="button"
                                class="text-amber-400 hover:text-amber-700 dark:hover:text-amber-300"
                                :disabled="isDeletingId === correction.id"
                                title="Supprimer"
                                @click="deleteAnnotation(correction)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ─── Panneau corrections — étudiant (lecture seule, toujours visible) ─ -->
        <div
            v-else-if="!estEnseignant && (corrections?.length ?? 0) > 0"
            class="flex w-48 shrink-0 flex-col gap-2"
        >
            <div
                v-for="correction in corrections"
                :key="correction.id"
                class="correction-card rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
                @mouseenter="highlightMark(correction.commentaire_id)"
                @mouseleave="highlightMark(null)"
            >
                <MessageSquare class="mb-1 h-3 w-3 text-amber-500" />
                <p>{{ correction.contenu }}</p>
            </div>
        </div>
    </div>
</template>

<style>
/* ─── Boutons de la toolbar ──────────────────────────────────────────────────── */
.tbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    padding: 4px;
    color: inherit;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.tbtn:hover  { background: hsl(var(--muted)); }
.tbtn.active { background: hsl(var(--muted)); color: hsl(var(--primary)); }

.tbtn-select {
    border-radius: 4px;
    padding: 2px 4px;
    font-size: 0.75rem;
    border: 1px solid hsl(var(--border));
    background: transparent;
    cursor: pointer;
    color: inherit;
    height: 28px;
}

/* Séparateur vertical */
.sep {
    width: 1px;
    align-self: stretch;
    background: hsl(var(--border));
    margin: 2px 2px;
}

/* ─── Palette de couleurs ────────────────────────────────────────────────────── */
.color-popup {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    z-index: 50;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid hsl(var(--border));
    background: hsl(var(--popover));
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
    min-width: 120px;
}

.swatch {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid rgba(0,0,0,.15);
    cursor: pointer;
    transition: transform 0.1s;
}
.swatch:hover { transform: scale(1.2); }

.reset-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    font-size: 10px;
    border-radius: 50%;
    border: 1px solid hsl(var(--border));
    background: transparent;
    cursor: pointer;
    color: hsl(var(--muted-foreground));
}
.reset-btn:hover { background: hsl(var(--muted)); }

/* ─── Placeholder TipTap ─────────────────────────────────────────────────────── */
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: hsl(var(--muted-foreground));
    pointer-events: none;
    height: 0;
}

/* ─── Styles du contenu ──────────────────────────────────────────────────────── */
.prose ul  { list-style-type: disc;    padding-left: 1.5rem; margin-bottom: 0.5rem; }
.prose ol  { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 0.5rem; }
.prose li  { margin-bottom: 2px; }
.prose p   { margin-bottom: 0.5rem; }
.prose blockquote {
    border-left: 3px solid hsl(var(--border));
    padding-left: 1rem;
    color: hsl(var(--muted-foreground));
    font-style: italic;
    margin: 0.5rem 0;
}
.prose h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.prose h2 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
.prose h3 { font-size: 1.1rem;  font-weight: 600; margin-bottom: 0.5rem; }
.prose hr  { border: none; border-top: 1px solid hsl(var(--border)); margin: 0.75rem 0; }
.prose strong { font-weight: 700; }
.prose em     { font-style: italic; }
.prose u      { text-decoration: underline; }
.prose s      { text-decoration: line-through; }
.prose a      { color: hsl(var(--primary)); text-decoration: underline; }
.prose a:hover { opacity: 0.8; }

/* ─── Marques de correction ──────────────────────────────────────────────────── */
mark.comment-mark {
    background: transparent;
    border-bottom: 2px solid #f97316;
    border-radius: 0;
    cursor: pointer;
    padding-bottom: 1px;
}

mark.comment-mark--active {
    background: #bfdbfe;
    border-bottom-color: #3b82f6;
}

/* ─── Bouton "Corriger" ──────────────────────────────────────────────────────── */
.corriger-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 5px;
    border: 1px solid #f97316;
    color: #ea580c;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    white-space: nowrap;
}
.corriger-btn:hover  { background: #fff7ed; }
.corriger-btn.active { background: #f97316; color: white; }

</style>
