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
import CommentaireEnseignant from '@/components/CommentaireEnseignant.vue';
import NotesGrille from '@/components/NotesGrille.vue';
import SommaireNotes from '@/components/SommaireNotes.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type Annotation = {
    id: number;
    commentaire_id: string;
    contenu: string;
    user_id: number;
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
    annotationsParChamp: Record<string, Annotation[]>;
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
    contenu:
        props.conclusions.find((c) => c.etudiant.id === userId.value)
            ?.contenu ?? '',
});

// ─── Auto-save ────────────────────────────────────────────────────────────────

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
const saveStatus = ref<SaveStatus>('idle');

let debounceShared: ReturnType<typeof setTimeout> | null = null;
let debounceConclusion: ReturnType<typeof setTimeout> | null = null;

const baseUrl = computed(
    () =>
        `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets`,
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
        await axios.put(`${baseUrl.value}/conclusion`, {
            contenu: maConclusion.contenu,
        });
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

/** Étudiant dont le sommaire de notes est affiché (null = fermé). */
const etudiantSommaireOuvert = ref<Etudiant | null>(null);

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

const codeComplet = computed(
    () => `${props.classe.code} / Gr. ${props.classe.groupe}`,
);

// ─── Table des matières ───────────────────────────────────────────────────────

const tocEntrees = computed(() => [
    { label: 'Introduction', numero: null },
    ...Array.from({ length: form.dev_count }, (_, i) => i + 1).map((n) => ({
        label:
            (form as any)[`dev_${n}_titre`] ||
            `Paragraphe de développement ${n}`,
        numero: n,
    })),
    ...props.membres.map((m) => ({
        label: `Conclusion — ${m.prenom} ${m.nom}`,
        numero: null,
    })),
]);

// ─── Commentaires de l'enseignant ─────────────────────────────────────────────

const commentaires = reactive<Record<string, Commentaire | null>>({
    ...Object.fromEntries(
        Object.entries(props.commentaires).map(([k, v]) => [k, v]),
    ),
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

// ─── Masquer/afficher les commentaires ───────────────────────────────────────

const commentairesReduits = reactive<Record<string, boolean>>({});

const champsVisibles = computed((): string[] => {
    const champs: string[] = [
        'normes_presentation',
        'introduction_amener',
        'introduction_poser',
        'introduction_diviser',
    ];
    for (let n = 1; n <= form.dev_count; n++) {
        champs.push(`dev_${n}_contenu`);
    }

    props.membres.forEach((m) => champs.push(`conclusion_${m.id}`));

    return champs.filter((c) => props.estEnseignant || commentaires[c]);
});

const tousCommentairesReduits = computed(
    () =>
        champsVisibles.value.length > 0 &&
        champsVisibles.value.every((c) => commentairesReduits[c]),
);

function toggleCommentaire(champ: string): void {
    commentairesReduits[champ] = !commentairesReduits[champ];
}

function toggleTousCommentaires(): void {
    if (tousCommentairesReduits.value) {
        champsVisibles.value.forEach((c) => {
            commentairesReduits[c] = false;
        });
    } else {
        champsVisibles.value.forEach((c) => {
            commentairesReduits[c] = true;
        });
    }
}

async function sauvegarderCommentaire(champ: string) {
    const contenu = brouillonsCommentaires[champ] ?? '';
    if (!contenu.trim()) return;
    commentairesSaving[champ] = true;
    try {
        const response = await axios.put(`${baseUrl.value}/commentaires`, {
            champ,
            contenu,
        });
        commentaires[champ] = {
            id: response.data.id,
            contenu: response.data.contenu,
        };
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

// ─── Annotations inline de l'enseignant ──────────────────────────────────────

const annotations = reactive<Record<string, Annotation[]>>({ ...props.annotationsParChamp });

async function sauvegarderAnnotation(
    champ: string,
    payload: { commentaire_id: string; contenu: string; html: string },
): Promise<void> {
    const response = await axios.put(`${baseUrl.value}/annotations`, { champ, ...payload });
    if (!annotations[champ]) {
        annotations[champ] = [];
    }
    annotations[champ].push({
        id: response.data.id,
        commentaire_id: response.data.commentaire_id,
        contenu: response.data.contenu,
        user_id: response.data.user_id,
    });
}

async function supprimerAnnotation(
    champ: string,
    payload: { correction: Annotation; html: string },
): Promise<void> {
    await axios.delete(`${baseUrl.value}/annotations/${payload.correction.id}`, {
        data: { champ, html: payload.html },
    });
    if (annotations[champ]) {
        annotations[champ] = annotations[champ].filter((a) => a.id !== payload.correction.id);
    }
}

// ─── Notes inline par étudiant ────────────────────────────────────────────────

// notes[userId][critere] = note | undefined
const notes = reactive<Record<number, Record<string, number | undefined>>>(
    Object.fromEntries(
        props.membres.map((m) => [
            m.id,
            Object.fromEntries(
                Object.keys(props.criteres).map((c) => [
                    c,
                    props.notesParEtudiant[m.id]?.[c],
                ]),
            ),
        ]),
    ),
);

// noteFinale[userId] = float | null
const noteFinale = reactive<Record<number, number | null>>({
    ...props.noteFinaleParEtudiant,
});

// Onglet étudiant actif par section — 'tous' = appliquer à tous les étudiants
const ongletActif = reactive<Record<string, number | 'tous'>>({});

function getOngletActif(section: string, fallback: number): number | 'tous' {
    if (ongletActif[section] === undefined) {
        // L'enseignant voit le premier étudiant ; l'étudiant voit son propre onglet
        ongletActif[section] = props.estEnseignant
            ? (props.membres[0]?.id ?? fallback)
            : userId.value;
    }

    return ongletActif[section];
}

function setOngletActif(section: string, membreId: number | 'tous') {
    ongletActif[section] = membreId;
}

/** Sauvegarde la même note pour tous les membres du groupe en parallèle. */
async function sauvegarderNotePourTous(
    section: string,
    critere: string,
    note: number,
) {
    const key = `${section}_${critere}_tous`;
    notesSaving[key] = true;
    try {
        await Promise.all(
            props.membres.map((m) =>
                sauvegarderNote(section, critere, m.id, note),
            ),
        );
    } finally {
        notesSaving[key] = false;
    }
}

const notesSaving = reactive<Record<string, boolean>>({});

async function sauvegarderNote(
    section: string,
    critere: string,
    membreId: number,
    note: number,
) {
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
        const nouvelles = response.data.noteFinaleParEtudiant as Record<
            number,
            number | null
        >;
        Object.entries(nouvelles).forEach(([uid, val]) => {
            noteFinale[Number(uid)] = val;
        });
    } finally {
        notesSaving[key] = false;
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="`Projet — ${groupe.nom}`" />

        <div class="mx-auto flex max-w-6xl flex-col gap-6 p-6">
            <!-- En-tête navigation -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="`/classes/${groupe.classe_id}/groupes/${groupe.id}/projets`"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Projets de recherche
                    </Link>
                </Button>

                <!-- Indicateur de sauvegarde -->
                <div
                    v-if="peutEditer"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Loader2
                        v-if="saveStatus === 'saving'"
                        class="h-4 w-4 animate-spin"
                    />
                    <CheckCircle2
                        v-else-if="saveStatus === 'saved'"
                        class="h-4 w-4 text-green-500"
                    />
                    <Cloud v-else class="h-4 w-4" />
                    <span v-if="saveStatus === 'saving'">Enregistrement…</span>
                    <span
                        v-else-if="saveStatus === 'saved'"
                        class="text-green-600"
                        >Enregistré</span
                    >
                    <span
                        v-else-if="saveStatus === 'error'"
                        class="text-destructive"
                        >Erreur d'enregistrement</span
                    >
                </div>
            </div>

            <Heading
                :title="groupe.nom"
                :description="`${classe.code} — ${classe.nom_cours}`"
            />

            <!-- Boutons d'export + notes finales par étudiant -->
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex flex-wrap gap-2">
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
                    <Button
                        v-if="champsVisibles.length > 0"
                        variant="ghost"
                        size="sm"
                        @click="toggleTousCommentaires"
                    >
                        <MessageSquare class="mr-2 h-4 w-4" />
                        {{
                            tousCommentairesReduits
                                ? 'Afficher les commentaires'
                                : 'Masquer les commentaires'
                        }}
                    </Button>
                </div>

                <!-- Note finale : enseignant voit tous ; étudiant voit uniquement la sienne -->
                <div class="flex flex-wrap gap-2">
                    <template v-if="estEnseignant">
                        <template v-for="membre in membres" :key="membre.id">
                            <button
                                v-if="
                                    noteFinale[membre.id] !== null &&
                                    noteFinale[membre.id] !== undefined
                                "
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-opacity hover:opacity-80"
                                :class="
                                    (noteFinale[membre.id] ?? 0) >= 60
                                        ? 'border-green-300 bg-green-50 text-green-700'
                                        : 'border-red-300 bg-red-50 text-red-700'
                                "
                                title="Voir le sommaire des notes"
                                @click="etudiantSommaireOuvert = membre"
                            >
                                <Star class="h-3.5 w-3.5" />
                                {{ membre.prenom }} :
                                {{ noteFinale[membre.id]?.toFixed(1) }} / 100
                            </button>
                        </template>
                    </template>
                    <button
                        v-else-if="
                            noteFinale[userId] !== null &&
                            noteFinale[userId] !== undefined
                        "
                        type="button"
                        class="flex items-center gap-2 rounded-lg border px-4 py-2 text-base font-semibold transition-opacity hover:opacity-80"
                        :class="
                            (noteFinale[userId] ?? 0) >= 60
                                ? 'border-green-300 bg-green-50 text-green-700'
                                : 'border-red-300 bg-red-50 text-red-700'
                        "
                        title="Voir le sommaire des notes"
                        @click="etudiantSommaireOuvert = membres.find((m) => m.id === userId) ?? null"
                    >
                        <Star class="h-4 w-4" />
                        Ma note : {{ noteFinale[userId]?.toFixed(1) }} / 100
                    </button>
                </div>
            </div>

            <!-- ─── Page titre ─────────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle
                        class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Page titre (générée automatiquement)
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('pageTitre')"
                    >
                        <ChevronUp
                            v-if="!collapsed.pageTitre"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.pageTitre">
                    <div v-if="peutEditer" class="mb-4">
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Titre du projet</Label
                        >
                        <Input
                            v-model="form.titre_projet"
                            placeholder="Ex. : L'agriculture québécoise à travers les époques"
                            class="text-center font-semibold uppercase"
                        />
                    </div>

                    <div
                        class="space-y-1 rounded-lg border bg-white p-6 text-center font-serif text-sm dark:bg-zinc-900"
                    >
                        <p
                            v-for="membre in membres"
                            :key="membre.id"
                            class="text-muted-foreground"
                        >
                            {{ membre.prenom }} {{ membre.nom }}
                        </p>
                        <p class="mt-2 text-muted-foreground">
                            {{ classe.nom_cours }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ codeComplet }}
                        </p>
                        <div class="py-4">
                            <p
                                class="text-lg font-bold tracking-wide uppercase"
                            >
                                {{ form.titre_projet || '(Titre du projet)' }}
                            </p>
                            <p class="mt-1 text-muted-foreground">
                                RECHERCHE DOCUMENTAIRE
                            </p>
                        </div>
                        <p class="text-muted-foreground">
                            Travail présenté à<br />
                            <span class="font-medium"
                                >{{ enseignant.prenom }}
                                {{ enseignant.nom }}</span
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Département des sciences humaines
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Cégep de Drummondville
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Le {{ dateAujourd }}
                        </p>
                    </div>

                    <!-- Commentaire enseignant -->
                    <CommentaireEnseignant
                        :commentaire="commentaires['normes_presentation']"
                        :brouillon="getBrouillon('normes_presentation')"
                        :est-reduit="
                            !!commentairesReduits['normes_presentation']
                        "
                        :is-saving="!!commentairesSaving['normes_presentation']"
                        :est-enseignant="estEnseignant"
                        placeholder="Commentaire sur les normes de présentation…"
                        class="mt-4"
                        @toggle="toggleCommentaire('normes_presentation')"
                        @save="sauvegarderCommentaire('normes_presentation')"
                        @delete="supprimerCommentaire('normes_presentation')"
                        @update:brouillon="
                            (v) => setBrouillon('normes_presentation', v)
                        "
                    />

                    <!-- Bloc note — Normes de présentation -->
                    <div
                        v-if="
                            estEnseignant ||
                            membres.some(
                                (m) =>
                                    notes[m.id]?.['normes_presentation'] !==
                                    undefined,
                            )
                        "
                        class="mt-4 space-y-2 rounded-lg border bg-muted/30 p-3"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            <Star class="h-3.5 w-3.5" />
                            Notes
                        </div>
                        <NotesGrille
                            section="page_titre"
                            :critere-keys="['normes_presentation']"
                            :critere-config="criteres"
                            :membres="membres"
                            :notes="notes"
                            :notes-saving="notesSaving"
                            :est-enseignant="estEnseignant"
                            :user-id="userId"
                            :onglet-actif="
                                getOngletActif(
                                    'page_titre',
                                    membres[0]?.id ?? 0,
                                )
                            "
                            @set-onglet="setOngletActif('page_titre', $event)"
                            @save-note="
                                (c, m, v) =>
                                    sauvegarderNote('page_titre', c, m, v)
                            "
                            @save-note-pour-tous="
                                (c, v) =>
                                    sauvegarderNotePourTous('page_titre', c, v)
                            "
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Table des matières ─────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle
                        class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Table des matières (générée automatiquement)
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('tdm')"
                    >
                        <ChevronUp v-if="!collapsed.tdm" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.tdm">
                    <div
                        class="space-y-1 rounded-lg border bg-white p-6 font-serif text-sm dark:bg-zinc-900"
                    >
                        <p class="mb-4 text-center font-bold">
                            TABLE DES MATIÈRES
                        </p>
                        <div
                            v-for="(entree, i) in tocEntrees"
                            :key="i"
                            class="flex items-baseline gap-1"
                        >
                            <span
                                v-if="entree.numero"
                                class="w-4 shrink-0 text-xs text-muted-foreground"
                                >{{ entree.numero }}.</span
                            >
                            <span v-else class="w-4 shrink-0" />
                            <span class="flex-1">{{ entree.label }}</span>
                            <span class="shrink-0 text-muted-foreground"
                                >…… p. X</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Introduction ───────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Introduction</CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('introduction')"
                    >
                        <ChevronUp
                            v-if="!collapsed.introduction"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.introduction" class="space-y-4">
                    <div class="flex border-b">
                        <button
                            v-for="tab in [
                                'amener',
                                'poser',
                                'diviser',
                            ] as const"
                            :key="tab"
                            type="button"
                            class="border-b-2 px-4 py-2 text-sm font-medium capitalize transition-colors"
                            :class="
                                introTab === tab
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            "
                            @click="introTab = tab"
                        >
                            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
                        </button>
                    </div>

                    <!-- Amener -->
                    <div v-show="introTab === 'amener'">
                        <p class="mb-2 text-xs text-muted-foreground">
                            Amenez le lecteur vers votre sujet (contexte
                            général, anecdote, statistique…)
                        </p>
                        <RichEditor
                            v-model="form.introduction_amener"
                            placeholder="Amener le sujet…"
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="annotations['introduction_amener'] ?? []"
                            @save-annotation="(p) => sauvegarderAnnotation('introduction_amener', p)"
                            @delete-annotation="(p) => supprimerAnnotation('introduction_amener', p)"
                        />
                        <CommentaireEnseignant
                            :commentaire="commentaires['introduction_amener']"
                            :brouillon="getBrouillon('introduction_amener')"
                            :est-reduit="
                                !!commentairesReduits['introduction_amener']
                            "
                            :is-saving="
                                !!commentairesSaving['introduction_amener']
                            "
                            :est-enseignant="estEnseignant"
                            class="mt-3"
                            @toggle="toggleCommentaire('introduction_amener')"
                            @save="
                                sauvegarderCommentaire('introduction_amener')
                            "
                            @delete="
                                supprimerCommentaire('introduction_amener')
                            "
                            @update:brouillon="
                                (v) => setBrouillon('introduction_amener', v)
                            "
                        />
                    </div>

                    <!-- Poser -->
                    <div v-show="introTab === 'poser'">
                        <p class="mb-2 text-xs text-muted-foreground">
                            Posez la question de recherche ou la problématique
                            centrale.
                        </p>
                        <RichEditor
                            v-model="form.introduction_poser"
                            placeholder="Poser le sujet…"
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="annotations['introduction_poser'] ?? []"
                            @save-annotation="(p) => sauvegarderAnnotation('introduction_poser', p)"
                            @delete-annotation="(p) => supprimerAnnotation('introduction_poser', p)"
                        />
                        <CommentaireEnseignant
                            :commentaire="commentaires['introduction_poser']"
                            :brouillon="getBrouillon('introduction_poser')"
                            :est-reduit="
                                !!commentairesReduits['introduction_poser']
                            "
                            :is-saving="
                                !!commentairesSaving['introduction_poser']
                            "
                            :est-enseignant="estEnseignant"
                            class="mt-3"
                            @toggle="toggleCommentaire('introduction_poser')"
                            @save="sauvegarderCommentaire('introduction_poser')"
                            @delete="supprimerCommentaire('introduction_poser')"
                            @update:brouillon="
                                (v) => setBrouillon('introduction_poser', v)
                            "
                        />
                    </div>

                    <!-- Diviser -->
                    <div v-show="introTab === 'diviser'">
                        <p class="mb-2 text-xs text-muted-foreground">
                            Divisez le plan : annoncez les grandes parties qui
                            seront développées.
                        </p>
                        <RichEditor
                            v-model="form.introduction_diviser"
                            placeholder="Diviser le sujet…"
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="annotations['introduction_diviser'] ?? []"
                            @save-annotation="(p) => sauvegarderAnnotation('introduction_diviser', p)"
                            @delete-annotation="(p) => supprimerAnnotation('introduction_diviser', p)"
                        />
                        <CommentaireEnseignant
                            :commentaire="commentaires['introduction_diviser']"
                            :brouillon="getBrouillon('introduction_diviser')"
                            :est-reduit="
                                !!commentairesReduits['introduction_diviser']
                            "
                            :is-saving="
                                !!commentairesSaving['introduction_diviser']
                            "
                            :est-enseignant="estEnseignant"
                            class="mt-3"
                            @toggle="toggleCommentaire('introduction_diviser')"
                            @save="
                                sauvegarderCommentaire('introduction_diviser')
                            "
                            @delete="
                                supprimerCommentaire('introduction_diviser')
                            "
                            @update:brouillon="
                                (v) => setBrouillon('introduction_diviser', v)
                            "
                        />
                    </div>

                    <!-- Bloc note — critère de l'onglet actif -->
                    <div
                        v-if="
                            estEnseignant ||
                            membres.some(
                                (m) =>
                                    notes[m.id]?.[introTabCritere[introTab]] !==
                                    undefined,
                            )
                        "
                        class="space-y-2 rounded-lg border bg-muted/30 p-3"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            <Star class="h-3.5 w-3.5" />Notes —
                            {{
                                introTab.charAt(0).toUpperCase() +
                                introTab.slice(1)
                            }}
                        </div>
                        <NotesGrille
                            :section="`intro_${introTab}`"
                            :critere-keys="[introTabCritere[introTab]]"
                            :critere-config="criteres"
                            :membres="membres"
                            :notes="notes"
                            :notes-saving="notesSaving"
                            :est-enseignant="estEnseignant"
                            :user-id="userId"
                            :onglet-actif="
                                getOngletActif(
                                    `intro_${introTab}`,
                                    membres[0]?.id ?? 0,
                                )
                            "
                            @set-onglet="
                                setOngletActif(`intro_${introTab}`, $event)
                            "
                            @save-note="
                                (c, m, v) =>
                                    sauvegarderNote(
                                        `intro_${introTab}`,
                                        c,
                                        m,
                                        v,
                                    )
                            "
                            @save-note-pour-tous="
                                (c, v) =>
                                    sauvegarderNotePourTous(
                                        `intro_${introTab}`,
                                        c,
                                        v,
                                    )
                            "
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Paragraphes de développement ──────────────────────────── -->
            <Card v-for="n in form.dev_count" :key="n">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                            >{{ n }}</span
                        >
                        <span
                            class="text-sm font-normal text-muted-foreground italic"
                        >
                            {{
                                (form as any)[`dev_${n}_titre`] ||
                                `Paragraphe de développement ${n}`
                            }}
                        </span>
                    </CardTitle>
                    <div class="flex items-center gap-1">
                        <Button
                            v-if="
                                peutEditer &&
                                n === form.dev_count &&
                                form.dev_count > 1
                            "
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-destructive"
                            title="Supprimer ce paragraphe"
                            @click="supprimerDev"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="toggleDev(n)"
                        >
                            <ChevronUp
                                v-if="!collapsedDev[n]"
                                class="h-4 w-4"
                            />
                            <ChevronDown v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-show="!collapsedDev[n]" class="space-y-2">
                    <div v-if="peutEditer" class="mb-1">
                        <Label class="text-xs text-muted-foreground"
                            >Titre du paragraphe</Label
                        >
                        <Input
                            :model-value="(form as any)[`dev_${n}_titre`]"
                            :placeholder="`Titre du paragraphe ${n}`"
                            class="mt-1"
                            @update:model-value="
                                (val: string) =>
                                    ((form as any)[`dev_${n}_titre`] = val)
                            "
                        />
                    </div>
                    <RichEditor
                        :model-value="(form as any)[`dev_${n}_contenu`]"
                        :placeholder="`Rédigez le contenu du paragraphe ${n}…`"
                        :read-only="!peutEditer"
                        :est-enseignant="estEnseignant"
                        :corrections="annotations[`dev_${n}_contenu`] ?? []"
                        @update:model-value="(val: string) => ((form as any)[`dev_${n}_contenu`] = val)"
                        @save-annotation="(p) => sauvegarderAnnotation(`dev_${n}_contenu`, p)"
                        @delete-annotation="(p) => supprimerAnnotation(`dev_${n}_contenu`, p)"
                    />

                    <!-- Commentaire -->
                    <CommentaireEnseignant
                        :commentaire="commentaires[`dev_${n}_contenu`]"
                        :brouillon="getBrouillon(`dev_${n}_contenu`)"
                        :est-reduit="!!commentairesReduits[`dev_${n}_contenu`]"
                        :is-saving="!!commentairesSaving[`dev_${n}_contenu`]"
                        :est-enseignant="estEnseignant"
                        class="mt-3"
                        @toggle="toggleCommentaire(`dev_${n}_contenu`)"
                        @save="sauvegarderCommentaire(`dev_${n}_contenu`)"
                        @delete="supprimerCommentaire(`dev_${n}_contenu`)"
                        @update:brouillon="
                            (v) => setBrouillon(`dev_${n}_contenu`, v)
                        "
                    />
                </CardContent>
            </Card>

            <!-- Bouton ajouter un paragraphe -->
            <div
                v-if="peutEditer && form.dev_count < 5"
                class="flex justify-center"
            >
                <Button variant="outline" size="sm" @click="ajouterDev">
                    <Plus class="mr-2 h-4 w-4" />
                    Ajouter un paragraphe de développement
                </Button>
            </div>

            <!-- ─── Notes — Développement ──────────────────────────────────── -->
            <Card
                v-if="
                    estEnseignant ||
                    membres.some((m) =>
                        criteresSections['developpement'].some(
                            (c) => notes[m.id]?.[c] !== undefined,
                        ),
                    )
                "
            >
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Star class="h-4 w-4 text-primary" />
                        Notes — Développement
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('developpement')"
                    >
                        <ChevronUp
                            v-if="!collapsed.developpement"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent
                    v-show="!collapsed.developpement"
                    class="space-y-3"
                >
                    <NotesGrille
                        section="developpement"
                        :critere-keys="criteresSections['developpement']"
                        :critere-config="criteres"
                        :membres="membres"
                        :notes="notes"
                        :notes-saving="notesSaving"
                        :est-enseignant="estEnseignant"
                        :user-id="userId"
                        :onglet-actif="
                            getOngletActif('developpement', membres[0]?.id ?? 0)
                        "
                        @set-onglet="setOngletActif('developpement', $event)"
                        @save-note="
                            (c, m, v) =>
                                sauvegarderNote('developpement', c, m, v)
                        "
                        @save-note-pour-tous="
                            (c, v) =>
                                sauvegarderNotePourTous('developpement', c, v)
                        "
                    />
                </CardContent>
            </Card>

            <!-- ─── Conclusions individuelles ──────────────────────────────── -->
            <Card v-for="item in conclusions" :key="item.etudiant.id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary"
                        >
                            {{ item.etudiant.prenom[0]
                            }}{{ item.etudiant.nom[0] }}
                        </span>
                        Conclusion — {{ item.etudiant.prenom }}
                        {{ item.etudiant.nom }}
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleConclusion(item.etudiant.id)"
                    >
                        <ChevronUp
                            v-if="!collapsedConclusion[item.etudiant.id]"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsedConclusion[item.etudiant.id]">
                    <template v-if="item.etudiant.id === userId && peutEditer">
                        <p class="mb-2 text-xs text-muted-foreground">
                            Synthèse des éléments développés et ouverture sur
                            une réflexion plus large.
                        </p>
                        <RichEditor
                            v-model="maConclusion.contenu"
                            placeholder="Rédigez votre conclusion…"
                            :est-enseignant="estEnseignant"
                        />
                    </template>
                    <template v-else>
                        <RichEditor
                            :model-value="item.contenu ?? ''"
                            :read-only="true"
                            :est-enseignant="estEnseignant"
                            placeholder="(Section non rédigée)"
                        />
                    </template>

                    <!-- Commentaire -->
                    <CommentaireEnseignant
                        :commentaire="
                            commentaires[`conclusion_${item.etudiant.id}`]
                        "
                        :brouillon="
                            getBrouillon(`conclusion_${item.etudiant.id}`)
                        "
                        :est-reduit="
                            !!commentairesReduits[
                                `conclusion_${item.etudiant.id}`
                            ]
                        "
                        :is-saving="
                            !!commentairesSaving[
                                `conclusion_${item.etudiant.id}`
                            ]
                        "
                        :est-enseignant="estEnseignant"
                        class="mt-3"
                        @toggle="
                            toggleCommentaire(`conclusion_${item.etudiant.id}`)
                        "
                        @save="
                            sauvegarderCommentaire(
                                `conclusion_${item.etudiant.id}`,
                            )
                        "
                        @delete="
                            supprimerCommentaire(
                                `conclusion_${item.etudiant.id}`,
                            )
                        "
                        @update:brouillon="
                            (v) =>
                                setBrouillon(
                                    `conclusion_${item.etudiant.id}`,
                                    v,
                                )
                        "
                    />

                    <!-- Bloc note — Conclusion -->
                    <div
                        v-if="
                            estEnseignant ||
                            (item.etudiant.id === userId &&
                                criteresSections['conclusion'].some(
                                    (c) => notes[userId]?.[c] !== undefined,
                                ))
                        "
                        class="mt-4 space-y-2 rounded-lg border bg-muted/30 p-3"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            <Star class="h-3.5 w-3.5" />Notes — Conclusion
                        </div>
                        <NotesGrille
                            :section="`conclusion_${item.etudiant.id}`"
                            :critere-keys="criteresSections['conclusion']"
                            :critere-config="criteres"
                            :membres="membres"
                            :membre-verrouille="item.etudiant.id"
                            :notes="notes"
                            :notes-saving="notesSaving"
                            :est-enseignant="estEnseignant"
                            :user-id="userId"
                            :onglet-actif="
                                getOngletActif(
                                    `conclusion_${item.etudiant.id}`,
                                    item.etudiant.id,
                                )
                            "
                            @set-onglet="
                                setOngletActif(
                                    `conclusion_${item.etudiant.id}`,
                                    $event,
                                )
                            "
                            @save-note="
                                (c, m, v) =>
                                    sauvegarderNote(
                                        `conclusion_${item.etudiant.id}`,
                                        c,
                                        m,
                                        v,
                                    )
                            "
                            @save-note-pour-tous="
                                (c, v) =>
                                    sauvegarderNotePourTous(
                                        `conclusion_${item.etudiant.id}`,
                                        c,
                                        v,
                                    )
                            "
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Notes — Références & Écriture ─────────────────────────── -->
            <Card
                v-if="
                    estEnseignant ||
                    membres.some((m) =>
                        criteresSections['references_et_ecriture'].some(
                            (c) => notes[m.id]?.[c] !== undefined,
                        ),
                    )
                "
            >
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Star class="h-4 w-4 text-primary" />
                        Notes — Références &amp; Écriture
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('references')"
                    >
                        <ChevronUp
                            v-if="!collapsed.references"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.references" class="space-y-3">
                    <NotesGrille
                        section="references_et_ecriture"
                        :critere-keys="
                            criteresSections['references_et_ecriture']
                        "
                        :critere-config="criteres"
                        :membres="membres"
                        :notes="notes"
                        :notes-saving="notesSaving"
                        :est-enseignant="estEnseignant"
                        :user-id="userId"
                        :onglet-actif="
                            getOngletActif(
                                'references_et_ecriture',
                                membres[0]?.id ?? 0,
                            )
                        "
                        @set-onglet="
                            setOngletActif('references_et_ecriture', $event)
                        "
                        @save-note="
                            (c, m, v) =>
                                sauvegarderNote(
                                    'references_et_ecriture',
                                    c,
                                    m,
                                    v,
                                )
                        "
                        @save-note-pour-tous="
                            (c, v) =>
                                sauvegarderNotePourTous(
                                    'references_et_ecriture',
                                    c,
                                    v,
                                )
                        "
                    />
                </CardContent>
            </Card>

            <!-- Bouton sauvegarder manuel -->
            <div v-if="peutEditer" class="flex justify-end gap-3 pb-4">
                <Button :disabled="saveStatus === 'saving'" @click="save">
                    <Loader2
                        v-if="saveStatus === 'saving'"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    <CheckCircle2
                        v-else-if="saveStatus === 'saved'"
                        class="mr-2 h-4 w-4"
                    />
                    Enregistrer
                </Button>
            </div>
        </div>

        <!-- Sommaire des notes d'un étudiant (ouvert au clic sur son nom) -->
        <SommaireNotes
            :open="etudiantSommaireOuvert !== null"
            :etudiant="etudiantSommaireOuvert"
            :notes="etudiantSommaireOuvert ? (notes[etudiantSommaireOuvert.id] ?? {}) : {}"
            :criteres="criteres"
            :criteres-sections="criteresSections"
            :est-enseignant="estEnseignant"
            @update:open="(v) => { if (!v) etudiantSommaireOuvert = null }"
            @save-note="(c, v) => etudiantSommaireOuvert && sauvegarderNote('sommaire', c, etudiantSommaireOuvert.id, v)"
        />
    </AppLayout>
</template>
