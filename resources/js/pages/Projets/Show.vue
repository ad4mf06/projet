<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';
import axios from 'axios';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Cloud,
    Download,
    FileText,
    Loader2,
    MessageSquare,
    Plus,
    Star,
    Trash2,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import RichEditor from '@/components/RichEditor.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';

// ─── Types ────────────────────────────────────────────────────────────────────

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type Enseignant = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
};

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Groupe = {
    id: number;
    nom: string;
    classe_id: number;
    membres: Etudiant[];
    thematiques: Thematique[];
};

type Projet = {
    id: number;
    groupe_id: number;
    dev_count: number;
    titre_projet: string | null;
    introduction_amener: string | null;
    introduction_poser: string | null;
    introduction_diviser: string | null;
    dev_1_titre: string | null;
    dev_1_contenu: string | null;
    dev_2_titre: string | null;
    dev_2_contenu: string | null;
    dev_3_titre: string | null;
    dev_3_contenu: string | null;
    dev_4_titre: string | null;
    dev_4_contenu: string | null;
    dev_5_titre: string | null;
    dev_5_contenu: string | null;
};

type ConclusionMembre = {
    etudiant: Etudiant;
    contenu: string | null;
};

type Commentaire = {
    id: number;
    contenu: string;
};

type CritereConfig = {
    label: string;
    poids: number;
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    enseignant: Enseignant;
    membres: Etudiant[];
    projet: Projet;
    conclusions: ConclusionMembre[];
    peutEditer: boolean;
    estEnseignant: boolean;
    commentaires: Record<string, Commentaire>;
    /** notes[userId][critere] = note */
    notesParEtudiant: Record<number, Record<string, number>>;
    /** noteFinaleParEtudiant[userId] = float | null */
    noteFinaleParEtudiant: Record<number, number | null>;
    criteres: Record<string, CritereConfig>;
    criteresSections: Record<string, string[]>;
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);

// ─── Contenu partagé ──────────────────────────────────────────────────────────

type FormPartagé = Omit<Projet, 'id' | 'groupe_id'>;

const form = reactive<FormPartagé>({
    dev_count: props.projet.dev_count ?? 1,
    titre_projet: props.projet.titre_projet ?? '',
    introduction_amener: props.projet.introduction_amener ?? '',
    introduction_poser: props.projet.introduction_poser ?? '',
    introduction_diviser: props.projet.introduction_diviser ?? '',
    dev_1_titre: props.projet.dev_1_titre ?? '',
    dev_1_contenu: props.projet.dev_1_contenu ?? '',
    dev_2_titre: props.projet.dev_2_titre ?? '',
    dev_2_contenu: props.projet.dev_2_contenu ?? '',
    dev_3_titre: props.projet.dev_3_titre ?? '',
    dev_3_contenu: props.projet.dev_3_contenu ?? '',
    dev_4_titre: props.projet.dev_4_titre ?? '',
    dev_4_contenu: props.projet.dev_4_contenu ?? '',
    dev_5_titre: props.projet.dev_5_titre ?? '',
    dev_5_contenu: props.projet.dev_5_contenu ?? '',
});

// ─── Conclusion de l'étudiant connecté ───────────────────────────────────────

const maConclusion = reactive({
    contenu: props.conclusions.find((c) => c.etudiant.id === userId.value)?.contenu ?? '',
});

// ─── Auto-save ────────────────────────────────────────────────────────────────

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
const saveStatus = ref<SaveStatus>('idle');

let debounceShared: ReturnType<typeof setTimeout> | null = null;
let debounceConclusion: ReturnType<typeof setTimeout> | null = null;

const baseUrl = computed(
    () => `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets`,
);

function scheduleSharedSave() {
    if (!props.peutEditer) return;
    saveStatus.value = 'saving';
    if (debounceShared) clearTimeout(debounceShared);
    debounceShared = setTimeout(() => saveShared(), 1500);
}

function scheduleConclusionSave() {
    if (!props.peutEditer) return;
    saveStatus.value = 'saving';
    if (debounceConclusion) clearTimeout(debounceConclusion);
    debounceConclusion = setTimeout(() => saveConclusion(), 1500);
}

async function saveShared() {
    if (!props.peutEditer) return;
    try {
        await axios.put(baseUrl.value, form);
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function saveConclusion() {
    if (!props.peutEditer) return;
    try {
        await axios.put(`${baseUrl.value}/conclusion`, { contenu: maConclusion.contenu });
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function save() {
    if (!props.peutEditer) return;
    saveStatus.value = 'saving';
    await Promise.all([saveShared(), saveConclusion()]);
}

watch(form, scheduleSharedSave, { deep: true });
watch(maConclusion, scheduleConclusionSave, { deep: true });

// ─── Paragraphes de développement dynamiques ─────────────────────────────────

function ajouterDev() {
    if (form.dev_count < 5) {
        form.dev_count++;
    }
}

function supprimerDev() {
    if (form.dev_count > 1) {
        form.dev_count--;
    }
}

// ─── Collapse / expand des sections ──────────────────────────────────────────

const collapsed = reactive<Record<string, boolean>>({
    pageTitre: false,
    tdm: false,
    introduction: false,
    developpement: false,
    references: false,
});

const collapsedDev = reactive<Record<number, boolean>>({});
const collapsedConclusion = reactive<Record<number, boolean>>({});

function toggleSection(key: string) {
    collapsed[key] = !collapsed[key];
}

function toggleDev(n: number) {
    collapsedDev[n] = !collapsedDev[n];
}

function toggleConclusion(id: number) {
    collapsedConclusion[id] = !collapsedConclusion[id];
}

// ─── Onglet d'introduction actif ──────────────────────────────────────────────

type IntroTab = 'amener' | 'poser' | 'diviser';
const introTab = ref<IntroTab>('amener');

const introTabCritere: Record<IntroTab, string> = {
    amener: 'introduction_amener',
    poser: 'introduction_poser',
    diviser: 'introduction_diviser',
};

// ─── Données pour la page titre ──────────────────────────────────────────────

const dateAujourd = computed(() =>
    new Date().toLocaleDateString('fr-CA', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const codeComplet = computed(() => `${props.classe.code} / Gr. ${props.classe.groupe}`);

// ─── Table des matières ───────────────────────────────────────────────────────

const tocEntrees = computed(() => [
    { label: 'Introduction', numero: null },
    ...Array.from({ length: form.dev_count }, (_, i) => i + 1).map((n) => ({
        label: (form as any)[`dev_${n}_titre`] || `Paragraphe de développement ${n}`,
        numero: n,
    })),
    ...props.membres.map((m) => ({
        label: `Conclusion — ${m.prenom} ${m.nom}`,
        numero: null,
    })),
]);

// ─── Commentaires de l'enseignant ─────────────────────────────────────────────

const commentaires = reactive<Record<string, Commentaire | null>>({
    ...Object.fromEntries(Object.entries(props.commentaires).map(([k, v]) => [k, v])),
});

const brouillonsCommentaires = reactive<Record<string, string>>({});

function getBrouillon(champ: string): string {
    if (brouillonsCommentaires[champ] === undefined) {
        brouillonsCommentaires[champ] = commentaires[champ]?.contenu ?? '';
    }

    return brouillonsCommentaires[champ];
}

function setBrouillon(champ: string, val: string) {
    brouillonsCommentaires[champ] = val;
}

const commentairesSaving = reactive<Record<string, boolean>>({});

async function sauvegarderCommentaire(champ: string) {
    const contenu = brouillonsCommentaires[champ] ?? '';
    if (!contenu.trim()) return;
    commentairesSaving[champ] = true;
    try {
        const response = await axios.put(`${baseUrl.value}/commentaires`, { champ, contenu });
        commentaires[champ] = { id: response.data.id, contenu: response.data.contenu };
    } finally {
        commentairesSaving[champ] = false;
    }
}

async function supprimerCommentaire(champ: string) {
    const c = commentaires[champ];
    if (!c) return;
    await axios.delete(`${baseUrl.value}/commentaires/${c.id}`);
    commentaires[champ] = null;
    brouillonsCommentaires[champ] = '';
}

// ─── Notes inline par étudiant ────────────────────────────────────────────────

// notes[userId][critere] = note | undefined
const notes = reactive<Record<number, Record<string, number | undefined>>>(
    Object.fromEntries(
        props.membres.map((m) => [
            m.id,
            Object.fromEntries(
                Object.keys(props.criteres).map((c) => [c, props.notesParEtudiant[m.id]?.[c]]),
            ),
        ]),
    ),
);

// noteFinale[userId] = float | null
const noteFinale = reactive<Record<number, number | null>>({ ...props.noteFinaleParEtudiant });

// Onglet étudiant actif par section — 'tous' = appliquer à tous les étudiants
const ongletActif = reactive<Record<string, number | 'tous'>>({});

function getOngletActif(section: string, fallback: number): number | 'tous' {
    if (ongletActif[section] === undefined) {
        // L'enseignant voit le premier étudiant ; l'étudiant voit son propre onglet
        ongletActif[section] = props.estEnseignant ? (props.membres[0]?.id ?? fallback) : userId.value;
    }

    return ongletActif[section];
}

function setOngletActif(section: string, membreId: number | 'tous') {
    ongletActif[section] = membreId;
}

/** Retourne true si tous les membres ont exactement cette valeur pour le critère. */
function tousOntNote(critere: string, valeur: number): boolean {
    return props.membres.length > 0 && props.membres.every((m) => notes[m.id]?.[critere] === valeur);
}

/** Sauvegarde la même note pour tous les membres du groupe en parallèle. */
async function sauvegarderNotePourTous(section: string, critere: string, note: number) {
    const key = `${section}_${critere}_tous`;
    notesSaving[key] = true;
    try {
        await Promise.all(props.membres.map((m) => sauvegarderNote(section, critere, m.id, note)));
    } finally {
        notesSaving[key] = false;
    }
}

const notesSaving = reactive<Record<string, boolean>>({});

async function sauvegarderNote(section: string, critere: string, membreId: number, note: number) {
    const key = `${section}_${critere}_${membreId}`;
    notesSaving[key] = true;

    if (!notes[membreId]) {
        notes[membreId] = {};
    }

    notes[membreId][critere] = note;

    try {
        const response = await axios.put(`${baseUrl.value}/notes`, {
            critere,
            note,
            user_id: membreId,
        });

        // Mettre à jour toutes les notes finales retournées
        const nouvelles = response.data.noteFinaleParEtudiant as Record<number, number | null>;
        Object.entries(nouvelles).forEach(([uid, val]) => {
            noteFinale[Number(uid)] = val;
        });
    } finally {
        notesSaving[key] = false;
    }
}

const labelNote: Record<number, string> = {
    0: 'Mauvais',
    2: 'Passable',
    3: 'Bon',
    4: 'Excellent',
};

const couleurNoteActif: Record<number, string> = {
    0: 'bg-red-100 text-red-700 border-red-400',
    2: 'bg-yellow-100 text-yellow-700 border-yellow-400',
    3: 'bg-blue-100 text-blue-700 border-blue-400',
    4: 'bg-green-100 text-green-700 border-green-400',
};
</script>

<template>
    <AppLayout>
        <Head :title="`Projet — ${groupe.nom}`" />

        <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto">
            <!-- En-tête navigation -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/classes/${groupe.classe_id}/groupes/${groupe.id}/projets`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Projets de recherche
                    </Link>
                </Button>

                <!-- Indicateur de sauvegarde -->
                <div v-if="peutEditer" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 v-if="saveStatus === 'saving'" class="h-4 w-4 animate-spin" />
                    <CheckCircle2 v-else-if="saveStatus === 'saved'" class="h-4 w-4 text-green-500" />
                    <Cloud v-else class="h-4 w-4" />
                    <span v-if="saveStatus === 'saving'">Enregistrement…</span>
                    <span v-else-if="saveStatus === 'saved'" class="text-green-600">Enregistré</span>
                    <span v-else-if="saveStatus === 'error'" class="text-destructive">Erreur d'enregistrement</span>
                </div>
            </div>

            <Heading
                :title="groupe.nom"
                :description="`${classe.code} — ${classe.nom_cours}`"
            />

            <!-- Boutons d'export + notes finales par étudiant -->
            <div class="flex gap-2 flex-wrap items-start justify-between">
                <div class="flex gap-2 flex-wrap">
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/pdf`" target="_blank">
                            <FileText class="mr-2 h-4 w-4" />
                            Exporter en PDF
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/word`">
                            <Download class="mr-2 h-4 w-4" />
                            Exporter en Word
                        </a>
                    </Button>
                </div>

                <!-- Note finale : enseignant voit tous ; étudiant voit uniquement la sienne -->
                <div class="flex flex-wrap gap-2">
                    <template v-if="estEnseignant">
                        <template v-for="membre in membres" :key="membre.id">
                            <div
                                v-if="noteFinale[membre.id] !== null && noteFinale[membre.id] !== undefined"
                                class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium"
                                :class="(noteFinale[membre.id] ?? 0) >= 60
                                    ? 'border-green-300 bg-green-50 text-green-700'
                                    : 'border-red-300 bg-red-50 text-red-700'"
                            >
                                <Star class="h-3.5 w-3.5" />
                                {{ membre.prenom }} : {{ noteFinale[membre.id]?.toFixed(1) }} / 100
                            </div>
                        </template>
                    </template>
                    <div
                        v-else-if="noteFinale[userId] !== null && noteFinale[userId] !== undefined"
                        class="flex items-center gap-2 rounded-lg border px-4 py-2 text-base font-semibold"
                        :class="(noteFinale[userId] ?? 0) >= 60
                            ? 'border-green-300 bg-green-50 text-green-700'
                            : 'border-red-300 bg-red-50 text-red-700'"
                    >
                        <Star class="h-4 w-4" />
                        Ma note : {{ noteFinale[userId]?.toFixed(1) }} / 100
                    </div>
                </div>
            </div>

            <!-- ─── Page titre ─────────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase tracking-wide">
                        Page titre (générée automatiquement)
                    </CardTitle>
                    <Button variant="ghost" size="icon" @click="toggleSection('pageTitre')">
                        <ChevronUp v-if="!collapsed.pageTitre" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.pageTitre">
                    <div v-if="peutEditer" class="mb-4">
                        <Label class="text-xs text-muted-foreground mb-1 block">Titre du projet</Label>
                        <Input
                            v-model="form.titre_projet"
                            placeholder="Ex. : L'agriculture québécoise à travers les époques"
                            class="text-center font-semibold uppercase"
                        />
                    </div>

                    <div class="rounded-lg border bg-white dark:bg-zinc-900 p-6 text-center font-serif space-y-1 text-sm">
                        <p v-for="membre in membres" :key="membre.id" class="text-muted-foreground">
                            {{ membre.prenom }} {{ membre.nom }}
                        </p>
                        <p class="text-muted-foreground mt-2">{{ classe.nom_cours }}</p>
                        <p class="text-muted-foreground text-xs">{{ codeComplet }}</p>
                        <div class="py-4">
                            <p class="text-lg font-bold uppercase tracking-wide">
                                {{ form.titre_projet || '(Titre du projet)' }}
                            </p>
                            <p class="text-muted-foreground mt-1">RECHERCHE DOCUMENTAIRE</p>
                        </div>
                        <p class="text-muted-foreground">
                            Travail présenté à<br />
                            <span class="font-medium">{{ enseignant.prenom }} {{ enseignant.nom }}</span>
                        </p>
                        <p class="text-muted-foreground text-xs">Département des sciences humaines</p>
                        <p class="text-muted-foreground text-xs">Cégep de Drummondville</p>
                        <p class="text-muted-foreground text-xs">Le {{ dateAujourd }}</p>
                    </div>

                    <!-- Commentaire enseignant -->
                    <div v-if="estEnseignant || commentaires['normes_presentation']" class="mt-4">
                        <div v-if="estEnseignant" class="flex gap-2 items-start">
                            <MessageSquare class="h-4 w-4 mt-2 text-amber-500 shrink-0" />
                            <div class="flex-1 space-y-1">
                                <Textarea
                                    :model-value="getBrouillon('normes_presentation')"
                                    placeholder="Commentaire sur les normes de présentation…"
                                    class="text-sm min-h-[60px]"
                                    @update:model-value="(v: string) => setBrouillon('normes_presentation', v)"
                                />
                                <div class="flex gap-2">
                                    <Button size="sm" variant="outline" :disabled="commentairesSaving['normes_presentation']" @click="sauvegarderCommentaire('normes_presentation')">
                                        <Loader2 v-if="commentairesSaving['normes_presentation']" class="mr-1 h-3 w-3 animate-spin" />
                                        Commenter
                                    </Button>
                                    <Button v-if="commentaires['normes_presentation']" size="sm" variant="ghost" class="text-destructive" @click="supprimerCommentaire('normes_presentation')">
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div v-else-if="commentaires['normes_presentation']" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            <MessageSquare class="h-4 w-4 mt-0.5 shrink-0" />
                            <p>{{ commentaires['normes_presentation']?.contenu }}</p>
                        </div>
                    </div>

                    <!-- Bloc note — Normes de présentation -->
                    <div v-if="estEnseignant || membres.some(m => notes[m.id]?.['normes_presentation'] !== undefined)" class="mt-4 rounded-lg border bg-muted/30 p-3 space-y-2">
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <Star class="h-3.5 w-3.5" />
                            Notes
                        </div>
                        <!-- Onglets étudiants -->
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-if="estEnseignant"
                                type="button"
                                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                :class="getOngletActif('page_titre') === 'tous'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                @click="setOngletActif('page_titre', 'tous')"
                            >
                                Tous
                            </button>
                            <template v-for="membre in membres" :key="membre.id">
                                <button
                                    v-if="estEnseignant || membre.id === userId"
                                    type="button"
                                    class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                    :class="getOngletActif('page_titre') === membre.id
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                    @click="setOngletActif('page_titre', membre.id)"
                                >
                                    {{ membre.prenom }}
                                </button>
                            </template>
                        </div>
                        <!-- Onglet Tous -->
                        <div v-if="estEnseignant" v-show="getOngletActif('page_titre') === 'tous'" class="space-y-1.5">
                            <span class="text-xs text-muted-foreground">{{ criteres['normes_presentation'].label }} ({{ criteres['normes_presentation'].poids }}%)</span>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="valeur in [0, 2, 3, 4]"
                                    :key="valeur"
                                    type="button"
                                    :disabled="notesSaving['page_titre_normes_presentation_tous']"
                                    class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                    :class="tousOntNote('normes_presentation', valeur)
                                        ? couleurNoteActif[valeur]
                                        : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                    @click="sauvegarderNotePourTous('page_titre', 'normes_presentation', valeur)"
                                >
                                    {{ valeur }} — {{ labelNote[valeur] }}
                                </button>
                            </div>
                        </div>
                        <!-- Critère -->
                        <div v-for="membre in membres" :key="membre.id">
                            <div v-show="getOngletActif('page_titre') === membre.id" class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="text-xs text-muted-foreground">{{ criteres['normes_presentation'].label }} ({{ criteres['normes_presentation'].poids }}%)</span>
                                    <span v-if="notes[membre.id]?.['normes_presentation'] !== undefined" class="text-xs font-medium text-muted-foreground">
                                        {{ ((notes[membre.id]['normes_presentation']! / 4) * criteres['normes_presentation'].poids).toFixed(2) }} / {{ criteres['normes_presentation'].poids }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="valeur in [0, 2, 3, 4]"
                                        :key="valeur"
                                        type="button"
                                        :disabled="!estEnseignant || notesSaving[`page_titre_normes_presentation_${membre.id}`]"
                                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                        :class="notes[membre.id]?.['normes_presentation'] === valeur
                                            ? couleurNoteActif[valeur]
                                            : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                        @click="sauvegarderNote('page_titre', 'normes_presentation', membre.id, valeur)"
                                    >
                                        {{ valeur }} — {{ labelNote[valeur] }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Table des matières ─────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase tracking-wide">
                        Table des matières (générée automatiquement)
                    </CardTitle>
                    <Button variant="ghost" size="icon" @click="toggleSection('tdm')">
                        <ChevronUp v-if="!collapsed.tdm" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.tdm">
                    <div class="rounded-lg border bg-white dark:bg-zinc-900 p-6 font-serif text-sm space-y-1">
                        <p class="font-bold text-center mb-4">TABLE DES MATIÈRES</p>
                        <div v-for="(entree, i) in tocEntrees" :key="i" class="flex items-baseline gap-1">
                            <span v-if="entree.numero" class="text-muted-foreground text-xs w-4 shrink-0">{{ entree.numero }}.</span>
                            <span v-else class="w-4 shrink-0" />
                            <span class="flex-1">{{ entree.label }}</span>
                            <span class="shrink-0 text-muted-foreground">…… p. X</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Introduction ───────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Introduction</CardTitle>
                    <Button variant="ghost" size="icon" @click="toggleSection('introduction')">
                        <ChevronUp v-if="!collapsed.introduction" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.introduction" class="space-y-4">
                    <div class="flex border-b">
                        <button
                            v-for="tab in (['amener', 'poser', 'diviser'] as const)"
                            :key="tab"
                            type="button"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors capitalize"
                            :class="introTab === tab
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'"
                            @click="introTab = tab"
                        >
                            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
                        </button>
                    </div>

                    <!-- Amener -->
                    <div v-show="introTab === 'amener'">
                        <p class="text-xs text-muted-foreground mb-2">Amenez le lecteur vers votre sujet (contexte général, anecdote, statistique…)</p>
                        <RichEditor v-model="form.introduction_amener" placeholder="Amener le sujet…" :read-only="!peutEditer" />
                        <!-- Commentaire -->
                        <div v-if="estEnseignant || commentaires['introduction_amener']" class="mt-3">
                            <div v-if="estEnseignant" class="flex gap-2 items-start">
                                <MessageSquare class="h-4 w-4 mt-2 text-amber-500 shrink-0" />
                                <div class="flex-1 space-y-1">
                                    <Textarea :model-value="getBrouillon('introduction_amener')" placeholder="Commentaire…" class="text-sm min-h-[60px]" @update:model-value="(v: string) => setBrouillon('introduction_amener', v)" />
                                    <div class="flex gap-2">
                                        <Button size="sm" variant="outline" :disabled="commentairesSaving['introduction_amener']" @click="sauvegarderCommentaire('introduction_amener')">
                                            <Loader2 v-if="commentairesSaving['introduction_amener']" class="mr-1 h-3 w-3 animate-spin" />Commenter
                                        </Button>
                                        <Button v-if="commentaires['introduction_amener']" size="sm" variant="ghost" class="text-destructive" @click="supprimerCommentaire('introduction_amener')"><Trash2 class="h-3 w-3" /></Button>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="commentaires['introduction_amener']" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                <MessageSquare class="h-4 w-4 mt-0.5 shrink-0" /><p>{{ commentaires['introduction_amener']?.contenu }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Poser -->
                    <div v-show="introTab === 'poser'">
                        <p class="text-xs text-muted-foreground mb-2">Posez la question de recherche ou la problématique centrale.</p>
                        <RichEditor v-model="form.introduction_poser" placeholder="Poser le sujet…" :read-only="!peutEditer" />
                        <div v-if="estEnseignant || commentaires['introduction_poser']" class="mt-3">
                            <div v-if="estEnseignant" class="flex gap-2 items-start">
                                <MessageSquare class="h-4 w-4 mt-2 text-amber-500 shrink-0" />
                                <div class="flex-1 space-y-1">
                                    <Textarea :model-value="getBrouillon('introduction_poser')" placeholder="Commentaire…" class="text-sm min-h-[60px]" @update:model-value="(v: string) => setBrouillon('introduction_poser', v)" />
                                    <div class="flex gap-2">
                                        <Button size="sm" variant="outline" :disabled="commentairesSaving['introduction_poser']" @click="sauvegarderCommentaire('introduction_poser')">
                                            <Loader2 v-if="commentairesSaving['introduction_poser']" class="mr-1 h-3 w-3 animate-spin" />Commenter
                                        </Button>
                                        <Button v-if="commentaires['introduction_poser']" size="sm" variant="ghost" class="text-destructive" @click="supprimerCommentaire('introduction_poser')"><Trash2 class="h-3 w-3" /></Button>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="commentaires['introduction_poser']" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                <MessageSquare class="h-4 w-4 mt-0.5 shrink-0" /><p>{{ commentaires['introduction_poser']?.contenu }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Diviser -->
                    <div v-show="introTab === 'diviser'">
                        <p class="text-xs text-muted-foreground mb-2">Divisez le plan : annoncez les grandes parties qui seront développées.</p>
                        <RichEditor v-model="form.introduction_diviser" placeholder="Diviser le sujet…" :read-only="!peutEditer" />
                        <div v-if="estEnseignant || commentaires['introduction_diviser']" class="mt-3">
                            <div v-if="estEnseignant" class="flex gap-2 items-start">
                                <MessageSquare class="h-4 w-4 mt-2 text-amber-500 shrink-0" />
                                <div class="flex-1 space-y-1">
                                    <Textarea :model-value="getBrouillon('introduction_diviser')" placeholder="Commentaire…" class="text-sm min-h-[60px]" @update:model-value="(v: string) => setBrouillon('introduction_diviser', v)" />
                                    <div class="flex gap-2">
                                        <Button size="sm" variant="outline" :disabled="commentairesSaving['introduction_diviser']" @click="sauvegarderCommentaire('introduction_diviser')">
                                            <Loader2 v-if="commentairesSaving['introduction_diviser']" class="mr-1 h-3 w-3 animate-spin" />Commenter
                                        </Button>
                                        <Button v-if="commentaires['introduction_diviser']" size="sm" variant="ghost" class="text-destructive" @click="supprimerCommentaire('introduction_diviser')"><Trash2 class="h-3 w-3" /></Button>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="commentaires['introduction_diviser']" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                <MessageSquare class="h-4 w-4 mt-0.5 shrink-0" /><p>{{ commentaires['introduction_diviser']?.contenu }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bloc note — critère de l'onglet actif -->
                    <div v-if="estEnseignant || membres.some(m => notes[m.id]?.[introTabCritere[introTab]] !== undefined)" class="rounded-lg border bg-muted/30 p-3 space-y-2">
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <Star class="h-3.5 w-3.5" />Notes — {{ introTab.charAt(0).toUpperCase() + introTab.slice(1) }}
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-if="estEnseignant"
                                type="button"
                                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                :class="getOngletActif(`intro_${introTab}`) === 'tous'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                @click="setOngletActif(`intro_${introTab}`, 'tous')"
                            >
                                Tous
                            </button>
                            <template v-for="membre in membres" :key="membre.id">
                                <button
                                    v-if="estEnseignant || membre.id === userId"
                                    type="button"
                                    class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                    :class="getOngletActif(`intro_${introTab}`) === membre.id
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                    @click="setOngletActif(`intro_${introTab}`, membre.id)"
                                >
                                    {{ membre.prenom }}
                                </button>
                            </template>
                        </div>
                        <!-- Onglet Tous -->
                        <div v-if="estEnseignant" v-show="getOngletActif(`intro_${introTab}`) === 'tous'" class="space-y-1.5">
                            <span class="text-xs text-muted-foreground">{{ criteres[introTabCritere[introTab]].label }} ({{ criteres[introTabCritere[introTab]].poids }}%)</span>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="valeur in [0, 2, 3, 4]"
                                    :key="valeur"
                                    type="button"
                                    :disabled="notesSaving[`intro_${introTab}_${introTabCritere[introTab]}_tous`]"
                                    class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                    :class="tousOntNote(introTabCritere[introTab], valeur)
                                        ? couleurNoteActif[valeur]
                                        : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                    @click="sauvegarderNotePourTous(`intro_${introTab}`, introTabCritere[introTab], valeur)"
                                >
                                    {{ valeur }} — {{ labelNote[valeur] }}
                                </button>
                            </div>
                        </div>
                        <div v-for="membre in membres" :key="membre.id">
                            <div v-show="getOngletActif(`intro_${introTab}`) === membre.id" class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="text-xs text-muted-foreground">{{ criteres[introTabCritere[introTab]].label }} ({{ criteres[introTabCritere[introTab]].poids }}%)</span>
                                    <span v-if="notes[membre.id]?.[introTabCritere[introTab]] !== undefined" class="text-xs font-medium text-muted-foreground">
                                        {{ ((notes[membre.id][introTabCritere[introTab]]! / 4) * criteres[introTabCritere[introTab]].poids).toFixed(2) }} / {{ criteres[introTabCritere[introTab]].poids }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="valeur in [0, 2, 3, 4]"
                                        :key="valeur"
                                        type="button"
                                        :disabled="!estEnseignant || notesSaving[`intro_${introTab}_${introTabCritere[introTab]}_${membre.id}`]"
                                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                        :class="notes[membre.id]?.[introTabCritere[introTab]] === valeur
                                            ? couleurNoteActif[valeur]
                                            : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                        @click="sauvegarderNote(`intro_${introTab}`, introTabCritere[introTab], membre.id, valeur)"
                                    >
                                        {{ valeur }} — {{ labelNote[valeur] }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Paragraphes de développement ──────────────────────────── -->
            <Card v-for="n in form.dev_count" :key="n">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span class="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold shrink-0">{{ n }}</span>
                        <span class="text-muted-foreground italic text-sm font-normal">
                            {{ (form as any)[`dev_${n}_titre`] || `Paragraphe de développement ${n}` }}
                        </span>
                    </CardTitle>
                    <div class="flex items-center gap-1">
                        <Button v-if="peutEditer && n === form.dev_count && form.dev_count > 1" variant="ghost" size="icon" class="text-destructive h-8 w-8" title="Supprimer ce paragraphe" @click="supprimerDev">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" @click="toggleDev(n)">
                            <ChevronUp v-if="!collapsedDev[n]" class="h-4 w-4" />
                            <ChevronDown v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-show="!collapsedDev[n]" class="space-y-2">
                    <div v-if="peutEditer" class="mb-1">
                        <Label class="text-xs text-muted-foreground">Titre du paragraphe</Label>
                        <Input :model-value="(form as any)[`dev_${n}_titre`]" :placeholder="`Titre du paragraphe ${n}`" class="mt-1" @update:model-value="(val: string) => ((form as any)[`dev_${n}_titre`] = val)" />
                    </div>
                    <RichEditor :model-value="(form as any)[`dev_${n}_contenu`]" :placeholder="`Rédigez le contenu du paragraphe ${n}…`" :read-only="!peutEditer" @update:model-value="(val: string) => ((form as any)[`dev_${n}_contenu`] = val)" />

                    <!-- Commentaire -->
                    <div v-if="estEnseignant || commentaires[`dev_${n}_contenu`]" class="mt-3">
                        <div v-if="estEnseignant" class="flex gap-2 items-start">
                            <MessageSquare class="h-4 w-4 mt-2 text-amber-500 shrink-0" />
                            <div class="flex-1 space-y-1">
                                <Textarea :model-value="getBrouillon(`dev_${n}_contenu`)" placeholder="Commentaire…" class="text-sm min-h-[60px]" @update:model-value="(v: string) => setBrouillon(`dev_${n}_contenu`, v)" />
                                <div class="flex gap-2">
                                    <Button size="sm" variant="outline" :disabled="commentairesSaving[`dev_${n}_contenu`]" @click="sauvegarderCommentaire(`dev_${n}_contenu`)">
                                        <Loader2 v-if="commentairesSaving[`dev_${n}_contenu`]" class="mr-1 h-3 w-3 animate-spin" />Commenter
                                    </Button>
                                    <Button v-if="commentaires[`dev_${n}_contenu`]" size="sm" variant="ghost" class="text-destructive" @click="supprimerCommentaire(`dev_${n}_contenu`)"><Trash2 class="h-3 w-3" /></Button>
                                </div>
                            </div>
                        </div>
                        <div v-else-if="commentaires[`dev_${n}_contenu`]" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            <MessageSquare class="h-4 w-4 mt-0.5 shrink-0" /><p>{{ commentaires[`dev_${n}_contenu`]?.contenu }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Bouton ajouter un paragraphe -->
            <div v-if="peutEditer && form.dev_count < 5" class="flex justify-center">
                <Button variant="outline" size="sm" @click="ajouterDev">
                    <Plus class="mr-2 h-4 w-4" />
                    Ajouter un paragraphe de développement
                </Button>
            </div>

            <!-- Bloc note — Développement (affiché une seule fois après tous les paragraphes) -->
            <Card v-if="estEnseignant || membres.some(m => criteresSections['developpement'].some(c => notes[m.id]?.[c] !== undefined))">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Star class="h-4 w-4 text-primary" />
                        Notes — Développement
                    </CardTitle>
                    <Button variant="ghost" size="icon" @click="toggleSection('developpement')">
                        <ChevronUp v-if="!collapsed.developpement" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.developpement" class="space-y-3">
                    <!-- Onglets étudiants -->
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-if="estEnseignant"
                            type="button"
                            class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                            :class="getOngletActif('developpement') === 'tous'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="setOngletActif('developpement', 'tous')"
                        >
                            Tous
                        </button>
                        <template v-for="membre in membres" :key="membre.id">
                            <button
                                v-if="estEnseignant || membre.id === userId"
                                type="button"
                                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                :class="getOngletActif('developpement') === membre.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                @click="setOngletActif('developpement', membre.id)"
                            >
                                {{ membre.prenom }}
                            </button>
                        </template>
                    </div>
                    <!-- Onglet Tous -->
                    <div v-if="estEnseignant" v-show="getOngletActif('developpement') === 'tous'" class="space-y-3">
                        <div v-for="critere in criteresSections['developpement']" :key="critere" class="space-y-1.5">
                            <span class="text-xs text-muted-foreground">{{ criteres[critere].label }} ({{ criteres[critere].poids }}%)</span>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="valeur in [0, 2, 3, 4]"
                                    :key="valeur"
                                    type="button"
                                    :disabled="notesSaving[`developpement_${critere}_tous`]"
                                    class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                    :class="tousOntNote(critere, valeur)
                                        ? couleurNoteActif[valeur]
                                        : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                    @click="sauvegarderNotePourTous('developpement', critere, valeur)"
                                >
                                    {{ valeur }} — {{ labelNote[valeur] }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Critères -->
                    <div v-for="membre in membres" :key="membre.id">
                        <div v-show="getOngletActif('developpement') === membre.id" class="space-y-3">
                            <div v-for="critere in criteresSections['developpement']" :key="critere" class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="text-xs text-muted-foreground">{{ criteres[critere].label }} ({{ criteres[critere].poids }}%)</span>
                                    <span v-if="notes[membre.id]?.[critere] !== undefined" class="text-xs font-medium text-muted-foreground">
                                        {{ ((notes[membre.id][critere]! / 4) * criteres[critere].poids).toFixed(2) }} / {{ criteres[critere].poids }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="valeur in [0, 2, 3, 4]"
                                        :key="valeur"
                                        type="button"
                                        :disabled="!estEnseignant || notesSaving[`developpement_${critere}_${membre.id}`]"
                                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                        :class="notes[membre.id]?.[critere] === valeur
                                            ? couleurNoteActif[valeur]
                                            : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                        @click="sauvegarderNote('developpement', critere, membre.id, valeur)"
                                    >
                                        {{ valeur }} — {{ labelNote[valeur] }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Conclusions individuelles ──────────────────────────────── -->
            <Card v-for="item in conclusions" :key="item.etudiant.id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span class="bg-primary/10 text-primary flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                            {{ item.etudiant.prenom[0] }}{{ item.etudiant.nom[0] }}
                        </span>
                        Conclusion — {{ item.etudiant.prenom }} {{ item.etudiant.nom }}
                    </CardTitle>
                    <Button variant="ghost" size="icon" @click="toggleConclusion(item.etudiant.id)">
                        <ChevronUp v-if="!collapsedConclusion[item.etudiant.id]" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsedConclusion[item.etudiant.id]">
                    <template v-if="item.etudiant.id === userId && peutEditer">
                        <p class="text-xs text-muted-foreground mb-2">Synthèse des éléments développés et ouverture sur une réflexion plus large.</p>
                        <RichEditor v-model="maConclusion.contenu" placeholder="Rédigez votre conclusion…" />
                    </template>
                    <template v-else>
                        <RichEditor :model-value="item.contenu ?? ''" :read-only="true" placeholder="(Section non rédigée)" />
                    </template>

                    <!-- Commentaire -->
                    <div v-if="estEnseignant || commentaires[`conclusion_${item.etudiant.id}`]" class="mt-3">
                        <div v-if="estEnseignant" class="flex gap-2 items-start">
                            <MessageSquare class="h-4 w-4 mt-2 text-amber-500 shrink-0" />
                            <div class="flex-1 space-y-1">
                                <Textarea :model-value="getBrouillon(`conclusion_${item.etudiant.id}`)" placeholder="Commentaire…" class="text-sm min-h-[60px]" @update:model-value="(v: string) => setBrouillon(`conclusion_${item.etudiant.id}`, v)" />
                                <div class="flex gap-2">
                                    <Button size="sm" variant="outline" :disabled="commentairesSaving[`conclusion_${item.etudiant.id}`]" @click="sauvegarderCommentaire(`conclusion_${item.etudiant.id}`)">
                                        <Loader2 v-if="commentairesSaving[`conclusion_${item.etudiant.id}`]" class="mr-1 h-3 w-3 animate-spin" />Commenter
                                    </Button>
                                    <Button v-if="commentaires[`conclusion_${item.etudiant.id}`]" size="sm" variant="ghost" class="text-destructive" @click="supprimerCommentaire(`conclusion_${item.etudiant.id}`)"><Trash2 class="h-3 w-3" /></Button>
                                </div>
                            </div>
                        </div>
                        <div v-else-if="commentaires[`conclusion_${item.etudiant.id}`]" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            <MessageSquare class="h-4 w-4 mt-0.5 shrink-0" /><p>{{ commentaires[`conclusion_${item.etudiant.id}`]?.contenu }}</p>
                        </div>
                    </div>

                    <!-- Bloc note — Conclusion (onglet pré-sélectionné sur l'étudiant de la carte) -->
                    <div v-if="estEnseignant || (item.etudiant.id === userId && criteresSections['conclusion'].some(c => notes[userId]?.[c] !== undefined))" class="mt-4 rounded-lg border bg-muted/30 p-3 space-y-2">
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <Star class="h-3.5 w-3.5" />Notes — Conclusion
                        </div>
                        <!-- Onglets : pre-sélection sur l'étudiant de la carte -->
                        <div v-if="estEnseignant" class="flex flex-wrap gap-1">
                            <button
                                type="button"
                                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                :class="getOngletActif(`conclusion_${item.etudiant.id}`) === 'tous'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                @click="setOngletActif(`conclusion_${item.etudiant.id}`, 'tous')"
                            >
                                Tous
                            </button>
                            <button
                                v-for="membre in membres"
                                :key="membre.id"
                                type="button"
                                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                :class="getOngletActif(`conclusion_${item.etudiant.id}`) === membre.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                @click="setOngletActif(`conclusion_${item.etudiant.id}`, membre.id)"
                            >
                                {{ membre.prenom }}
                            </button>
                        </div>

                        <!-- Onglet Tous -->
                        <div v-if="estEnseignant" v-show="getOngletActif(`conclusion_${item.etudiant.id}`) === 'tous'" class="space-y-3">
                            <div v-for="critere in criteresSections['conclusion']" :key="critere" class="space-y-1.5">
                                <span class="text-xs text-muted-foreground">{{ criteres[critere].label }} ({{ criteres[critere].poids }}%)</span>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="valeur in [0, 2, 3, 4]"
                                        :key="valeur"
                                        type="button"
                                        :disabled="notesSaving[`conclusion_${item.etudiant.id}_${critere}_tous`]"
                                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                        :class="tousOntNote(critere, valeur)
                                            ? couleurNoteActif[valeur]
                                            : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                        @click="sauvegarderNotePourTous(`conclusion_${item.etudiant.id}`, critere, valeur)"
                                    >
                                        {{ valeur }} — {{ labelNote[valeur] }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Critères pour l'onglet actif (ou l'étudiant de la carte si étudiant) -->
                        <div v-for="membre in membres" :key="membre.id">
                            <div
                                v-show="estEnseignant
                                    ? getOngletActif(`conclusion_${item.etudiant.id}`) === membre.id
                                    : membre.id === userId"
                                class="space-y-3"
                            >
                                <div v-for="critere in criteresSections['conclusion']" :key="critere" class="space-y-1.5">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <span class="text-xs text-muted-foreground">{{ criteres[critere].label }} ({{ criteres[critere].poids }}%)</span>
                                        <span v-if="notes[membre.id]?.[critere] !== undefined" class="text-xs font-medium text-muted-foreground">
                                            {{ ((notes[membre.id][critere]! / 4) * criteres[critere].poids).toFixed(2) }} / {{ criteres[critere].poids }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <button
                                            v-for="valeur in [0, 2, 3, 4]"
                                            :key="valeur"
                                            type="button"
                                            :disabled="!estEnseignant || notesSaving[`conclusion_${item.etudiant.id}_${critere}_${membre.id}`]"
                                            class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                            :class="notes[membre.id]?.[critere] === valeur
                                                ? couleurNoteActif[valeur]
                                                : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                            @click="sauvegarderNote(`conclusion_${item.etudiant.id}`, critere, membre.id, valeur)"
                                        >
                                            {{ valeur }} — {{ labelNote[valeur] }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Références & Écriture ──────────────────────────────────── -->
            <Card v-if="estEnseignant || membres.some(m => criteresSections['references_et_ecriture'].some(c => notes[m.id]?.[c] !== undefined))">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Star class="h-4 w-4 text-primary" />
                        Notes — Références &amp; Écriture
                    </CardTitle>
                    <Button variant="ghost" size="icon" @click="toggleSection('references')">
                        <ChevronUp v-if="!collapsed.references" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.references" class="space-y-3">
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-if="estEnseignant"
                            type="button"
                            class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                            :class="getOngletActif('references_et_ecriture') === 'tous'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="setOngletActif('references_et_ecriture', 'tous')"
                        >
                            Tous
                        </button>
                        <template v-for="membre in membres" :key="membre.id">
                            <button
                                v-if="estEnseignant || membre.id === userId"
                                type="button"
                                class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                :class="getOngletActif('references_et_ecriture') === membre.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                @click="setOngletActif('references_et_ecriture', membre.id)"
                            >
                                {{ membre.prenom }}
                            </button>
                        </template>
                    </div>
                    <!-- Onglet Tous -->
                    <div v-if="estEnseignant" v-show="getOngletActif('references_et_ecriture') === 'tous'" class="space-y-3">
                        <div v-for="critere in criteresSections['references_et_ecriture']" :key="critere" class="space-y-1.5">
                            <span class="text-xs text-muted-foreground">{{ criteres[critere].label }} ({{ criteres[critere].poids }}%)</span>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="valeur in [0, 2, 3, 4]"
                                    :key="valeur"
                                    type="button"
                                    :disabled="notesSaving[`ref_${critere}_tous`]"
                                    class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                    :class="tousOntNote(critere, valeur)
                                        ? couleurNoteActif[valeur]
                                        : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                    @click="sauvegarderNotePourTous('references_et_ecriture', critere, valeur)"
                                >
                                    {{ valeur }} — {{ labelNote[valeur] }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-for="membre in membres" :key="membre.id">
                        <div v-show="getOngletActif('references_et_ecriture') === membre.id" class="space-y-3">
                            <div v-for="critere in criteresSections['references_et_ecriture']" :key="critere" class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="text-xs text-muted-foreground">{{ criteres[critere].label }} ({{ criteres[critere].poids }}%)</span>
                                    <span v-if="notes[membre.id]?.[critere] !== undefined" class="text-xs font-medium text-muted-foreground">
                                        {{ ((notes[membre.id][critere]! / 4) * criteres[critere].poids).toFixed(2) }} / {{ criteres[critere].poids }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="valeur in [0, 2, 3, 4]"
                                        :key="valeur"
                                        type="button"
                                        :disabled="!estEnseignant || notesSaving[`ref_${critere}_${membre.id}`]"
                                        class="rounded border px-2 py-0.5 text-xs font-medium transition-colors disabled:opacity-60"
                                        :class="notes[membre.id]?.[critere] === valeur
                                            ? couleurNoteActif[valeur]
                                            : 'border-border text-muted-foreground hover:border-primary hover:text-primary'"
                                        @click="sauvegarderNote('references_et_ecriture', critere, membre.id, valeur)"
                                    >
                                        {{ valeur }} — {{ labelNote[valeur] }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Bouton sauvegarder manuel -->
            <div v-if="peutEditer" class="flex justify-end gap-3 pb-4">
                <Button :disabled="saveStatus === 'saving'" @click="save">
                    <Loader2 v-if="saveStatus === 'saving'" class="mr-2 h-4 w-4 animate-spin" />
                    <CheckCircle2 v-else-if="saveStatus === 'saved'" class="mr-2 h-4 w-4" />
                    Enregistrer
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
