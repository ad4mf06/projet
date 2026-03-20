<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';
import axios from 'axios';
import { ArrowLeft, CheckCircle2, Cloud, Download, FileText, Loader2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import RichEditor from '@/components/RichEditor.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

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

type Props = {
    groupe: Groupe;
    classe: Classe;
    enseignant: Enseignant;
    membres: Etudiant[];
    projet: Projet;
    conclusions: ConclusionMembre[];
    peutEditer: boolean;
    estEnseignant: boolean;
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);

// ─── Contenu partagé ──────────────────────────────────────────────────────────
const form = reactive<Omit<Projet, 'id' | 'groupe_id'>>({
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
const completion = ref(0);

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
        const response = await axios.put(baseUrl.value, form);
        completion.value = response.data.completion;
        saveStatus.value = 'saved';
        setTimeout(() => { saveStatus.value = 'idle'; }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function saveConclusion() {
    if (!props.peutEditer) return;
    try {
        await axios.put(`${baseUrl.value}/conclusion`, { contenu: maConclusion.contenu });
        saveStatus.value = 'saved';
        setTimeout(() => { saveStatus.value = 'idle'; }, 2000);
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

// ─── Onglet d'introduction actif ──────────────────────────────────────────────
type IntroTab = 'amener' | 'poser' | 'diviser';
const introTab = ref<IntroTab>('amener');

// ─── Données pour la page titre (aperçu auto-généré) ─────────────────────────
const dateAujourd = computed(() =>
    new Date().toLocaleDateString('fr-CA', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const codeComplet = computed(() =>
    `${props.classe.code} / Gr. ${props.classe.groupe}`,
);

// ─── Table des matières (auto-générée depuis les titres) ──────────────────────
const tocEntrees = computed(() => [
    { label: 'Introduction', numero: null },
    ...[1, 2, 3, 4, 5].map((n) => ({
        label: (form as any)[`dev_${n}_titre`] || `Paragraphe de développement ${n}`,
        numero: n,
    })),
    // Autant d'entrées de conclusion que de membres
    ...props.membres.map((m) => ({
        label: `Conclusion — ${m.prenom} ${m.nom}`,
        numero: null,
    })),
]);
</script>

<template>
    <AppLayout>
        <Head :title="`Projet — ${groupe.nom}`" />

        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">
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

            <!-- Boutons d'export -->
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

            <!-- ─── Page titre (aperçu auto-généré) ────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase tracking-wide">
                        Page titre (générée automatiquement)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Titre du projet (éditable) -->
                    <div v-if="peutEditer" class="mb-4">
                        <Label class="text-xs text-muted-foreground mb-1 block">Titre du projet</Label>
                        <Input
                            v-model="form.titre_projet"
                            placeholder="Ex. : L'agriculture québécoise à travers les époques"
                            class="text-center font-semibold uppercase"
                        />
                    </div>

                    <!-- Aperçu : chaque membre sur sa propre ligne -->
                    <div class="rounded-lg border bg-white dark:bg-zinc-900 p-6 text-center font-serif space-y-1 text-sm">
                        <p
                            v-for="membre in membres"
                            :key="membre.id"
                            class="text-muted-foreground"
                        >
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
                </CardContent>
            </Card>

            <!-- ─── Table des matières (aperçu auto-généré) ────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase tracking-wide">
                        Table des matières (générée automatiquement)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-lg border bg-white dark:bg-zinc-900 p-6 font-serif text-sm space-y-1">
                        <p class="font-bold text-center mb-4">TABLE DES MATIÈRES</p>
                        <div
                            v-for="(entree, i) in tocEntrees"
                            :key="i"
                            class="flex items-baseline gap-1"
                        >
                            <span v-if="entree.numero" class="text-muted-foreground text-xs w-4 shrink-0">
                                {{ entree.numero }}.
                            </span>
                            <span v-else class="w-4 shrink-0" />
                            <span class="flex-1">{{ entree.label }}</span>
                            <span class="shrink-0 text-muted-foreground">…… p. X</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Introduction ───────────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle>Introduction</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
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

                    <div v-show="introTab === 'amener'">
                        <p class="text-xs text-muted-foreground mb-2">
                            Amenez le lecteur vers votre sujet (contexte général, anecdote, statistique…)
                        </p>
                        <RichEditor
                            v-model="form.introduction_amener"
                            placeholder="Amener le sujet…"
                            :read-only="!peutEditer"
                        />
                    </div>

                    <div v-show="introTab === 'poser'">
                        <p class="text-xs text-muted-foreground mb-2">
                            Posez la question de recherche ou la problématique centrale.
                        </p>
                        <RichEditor
                            v-model="form.introduction_poser"
                            placeholder="Poser le sujet…"
                            :read-only="!peutEditer"
                        />
                    </div>

                    <div v-show="introTab === 'diviser'">
                        <p class="text-xs text-muted-foreground mb-2">
                            Divisez le plan : annoncez les grandes parties qui seront développées.
                        </p>
                        <RichEditor
                            v-model="form.introduction_diviser"
                            placeholder="Diviser le sujet…"
                            :read-only="!peutEditer"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- ─── 5 paragraphes de développement ─────────────────────────── -->
            <Card
                v-for="n in 5"
                :key="n"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold shrink-0">
                            {{ n }}
                        </span>
                        <span v-if="!peutEditer || !(form as any)[`dev_${n}_titre`]" class="text-muted-foreground italic text-sm font-normal">
                            {{ (form as any)[`dev_${n}_titre`] || `Paragraphe de développement ${n}` }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div v-if="peutEditer" class="mb-1">
                        <Label class="text-xs text-muted-foreground">Titre du paragraphe</Label>
                        <Input
                            :model-value="(form as any)[`dev_${n}_titre`]"
                            :placeholder="`Titre du paragraphe ${n}`"
                            class="mt-1"
                            @update:model-value="(val: string) => ((form as any)[`dev_${n}_titre`] = val)"
                        />
                    </div>
                    <RichEditor
                        :model-value="(form as any)[`dev_${n}_contenu`]"
                        :placeholder="`Rédigez le contenu du paragraphe ${n}…`"
                        :read-only="!peutEditer"
                        @update:model-value="(val: string) => ((form as any)[`dev_${n}_contenu`] = val)"
                    />
                </CardContent>
            </Card>

            <!-- ─── Conclusions individuelles (une par membre) ─────────────── -->
            <Card
                v-for="item in conclusions"
                :key="item.etudiant.id"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span class="bg-primary/10 text-primary flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                            {{ item.etudiant.prenom[0] }}{{ item.etudiant.nom[0] }}
                        </span>
                        Conclusion — {{ item.etudiant.prenom }} {{ item.etudiant.nom }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- L'étudiant connecté peut modifier sa propre conclusion -->
                    <template v-if="item.etudiant.id === userId && peutEditer">
                        <p class="text-xs text-muted-foreground mb-2">
                            Synthèse des éléments développés et ouverture sur une réflexion plus large.
                        </p>
                        <RichEditor
                            v-model="maConclusion.contenu"
                            placeholder="Rédigez votre conclusion…"
                        />
                    </template>
                    <!-- Les autres conclusions sont en lecture seule -->
                    <template v-else>
                        <RichEditor
                            :model-value="item.contenu ?? ''"
                            :read-only="true"
                            placeholder="(Section non rédigée)"
                        />
                    </template>
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
