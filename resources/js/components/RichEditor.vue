<script setup lang="ts">
import CharacterCount from '@tiptap/extension-character-count';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Typography from '@tiptap/extension-typography';
import Underline from '@tiptap/extension-underline';
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
    Minus,
    Palette,
    Quote,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Underline as UnderlineIcon,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    maxLength?: number;
    readOnly?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// ─── Extensions ───────────────────────────────────────────────────────────────
const extensions = [
    StarterKit,
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
];

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
    { label: 'Noir',       value: '#000000' },
    { label: 'Gris',       value: '#6B7280' },
    { label: 'Rouge',      value: '#DC2626' },
    { label: 'Orange',     value: '#EA580C' },
    { label: 'Jaune',      value: '#CA8A04' },
    { label: 'Vert',       value: '#16A34A' },
    { label: 'Bleu',       value: '#2563EB' },
    { label: 'Violet',     value: '#7C3AED' },
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

function setTextColor(color: string) {
    editor.value?.chain().focus().setColor(color).run();
    showTextColorPicker.value = false;
}

function setHighlight(color: string) {
    editor.value?.chain().focus().setHighlight({ color }).run();
    showHighlightPicker.value = false;
}

function removeTextColor() {
    editor.value?.chain().focus().unsetColor().run();
    showTextColorPicker.value = false;
}

function removeHighlight() {
    editor.value?.chain().focus().unsetHighlight().run();
    showHighlightPicker.value = false;
}

// ─── Lien ─────────────────────────────────────────────────────────────────────
function toggleLink() {
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
    if (!editor.value) return 'p';
    if (editor.value.isActive('heading', { level: 1 })) return '1';
    if (editor.value.isActive('heading', { level: 2 })) return '2';
    if (editor.value.isActive('heading', { level: 3 })) return '3';
    return 'p';
}

function setHeading(val: string) {
    if (!editor.value) return;
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
</script>

<template>
    <div class="border-input rounded-md border bg-white dark:bg-zinc-900 overflow-hidden">

        <!-- ─── Barre d'outils ──────────────────────────────────────────────── -->
        <div
            v-if="!readOnly && editor"
            class="border-b border-input flex flex-wrap gap-x-0.5 gap-y-1 px-2 py-1.5"
        >
            <!-- Groupe 1 : Mise en forme de base -->
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
            <!-- Couleur de texte -->
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

            <!-- Surlignage -->
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
        </div>

        <!-- ─── Zone d'édition ──────────────────────────────────────────────── -->
        <EditorContent :editor="editor" />

        <!-- ─── Compteur de caractères ─────────────────────────────────────── -->
        <div
            v-if="maxLength && editor"
            class="px-3 py-1 text-xs text-muted-foreground text-right border-t border-input"
            :class="{ 'text-destructive': editor.storage.characterCount.characters() >= maxLength }"
        >
            {{ editor.storage.characterCount.characters() }} / {{ maxLength }}
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
</style>
