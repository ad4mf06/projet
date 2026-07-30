<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { AlertTriangle, CheckCircle2, Clock, Eye, EyeOff, ExternalLink, FileText, Users } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'
import * as museePublication from '@/actions/App/Http/Controllers/MuseePublicationController'

// ─── Types ────────────────────────────────────────────────────────────────────

type Cours = { id: number; nom_cours: string; code: string }
type TypeProjet = { id: number; nom: string }

type Groupe = {
    id: number
    nom: string
    classe_id: number
    classe_nom: string | null
    membres: string[]
}

type Publication = {
    statut: 'brouillon' | 'soumis' | 'approuve' | 'rejete'
    soumis_le: string | null
    publie_le: string | null
    raison_rejet: string | null
    est_publie: boolean
}

type Meta = {
    entete_titre: string | null
    slug: string | null
    intro_image_path: string | null
    entete_image_path: string | null
} | null

type Projet = {
    id: number
    groupe: Groupe
    publication: Publication
    meta: Meta
}

type Props = {
    cours: Cours
    typeProjet: TypeProjet
    projets: Projet[]
}

const props = defineProps<Props>()

// ─── Filtrage ─────────────────────────────────────────────────────────────────

type Filtre = 'tous' | 'brouillon' | 'soumis' | 'approuve' | 'rejete'

const filtreActif = ref<Filtre>('soumis')

const projetsFiltres = computed(() => {
    if (filtreActif.value === 'tous') return props.projets
    return props.projets.filter((p) => p.publication.statut === filtreActif.value)
})

const compteParStatut = computed(() => ({
    brouillon: props.projets.filter((p) => p.publication.statut === 'brouillon').length,
    soumis: props.projets.filter((p) => p.publication.statut === 'soumis').length,
    approuve: props.projets.filter((p) => p.publication.statut === 'approuve').length,
    rejete: props.projets.filter((p) => p.publication.statut === 'rejete').length,
}))

// ─── Actions ──────────────────────────────────────────────────────────────────

/** Construit les paramètres de route pour un projet donné. */
function routeParams(projet: Projet) {
    return {
        cours: props.cours.id,
        classe: projet.groupe.classe_id,
        groupe: projet.groupe.id,
        typeProjet: props.typeProjet.id,
    }
}

/** URL de la page d'édition du musée pour ce groupe. */
function urlMusee(projet: Projet): string {
    const p = routeParams(projet)
    return `/cours/${p.cours}/classes/${p.classe}/groupes/${p.groupe}/projets/${p.typeProjet}/musee`
}

function approuver(projet: Projet) {
    if (!window.confirm('Approuver et publier ce musée ?')) return
    router.post(museePublication.approuver.url(routeParams(projet)), {}, { preserveScroll: true })
}

function togglePublication(projet: Projet) {
    const action = projet.publication.est_publie ? 'Retirer ce musée de la galerie ?' : 'Publier ce musée directement dans la galerie ?'
    if (!window.confirm(action)) return
    router.post(museePublication.toggle.url(routeParams(projet)), {}, { preserveScroll: true })
}

/** État local du formulaire de rejet (par projet). */
const rejetOuvertId = ref<number | null>(null)
const raisonRejet = ref('')

function ouvrirRejet(projet: Projet) {
    rejetOuvertId.value = projet.id
    raisonRejet.value = ''
}

function fermerRejet() {
    rejetOuvertId.value = null
    raisonRejet.value = ''
}

function rejeter(projet: Projet) {
    if (!raisonRejet.value.trim()) return
    router.post(
        museePublication.rejeter.url(routeParams(projet)),
        { raison_rejet: raisonRejet.value },
        {
            preserveScroll: true,
            onSuccess: () => fermerRejet(),
        },
    )
}

// ─── Helpers d'affichage ──────────────────────────────────────────────────────

/** Retourne l'URL de miniature d'un projet (en-tête ou intro, selon disponibilité). */
function thumbnailUrl(projet: Projet): string | null {
    const path = projet.meta?.entete_titre ? projet.meta?.intro_image_path ?? projet.meta?.entete_image_path : projet.meta?.entete_image_path ?? projet.meta?.intro_image_path
    return path ? `/storage/${path}` : null
}

/** Formate une date ISO en chaîne lisible (ex : 22 juil. 2026). */
function formatDate(iso: string | null): string {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('fr-CA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="`Publications — ${typeProjet.nom}`" />

        <div class="mx-auto max-w-5xl px-4 py-8 space-y-6">

            <!-- En-tête -->
            <div>
                <Link
                    :href="`/cours/${cours.id}`"
                    class="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    ← Retour au cours
                </Link>
                <h1 class="text-xl font-bold">Gestion des publications</h1>
                <p class="text-sm text-muted-foreground">
                    {{ typeProjet.nom }} — {{ cours.code }}
                </p>
            </div>

            <!-- Onglets de filtrage -->
            <div class="flex flex-wrap gap-1 rounded-lg border bg-muted/40 p-1 w-fit">
                <button
                    v-for="(label, key) in {
                        tous: 'Tous',
                        brouillon: 'En cours',
                        soumis: 'En attente',
                        approuve: 'Approuvés',
                        rejete: 'Rejetés',
                    }"
                    :key="key"
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        filtreActif === key
                            ? 'bg-background shadow-sm text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="filtreActif = key as Filtre"
                >
                    {{ label }}
                    <span
                        v-if="key !== 'tous' && compteParStatut[key as 'brouillon' | 'soumis' | 'approuve' | 'rejete'] > 0"
                        :class="[
                            'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                            key === 'brouillon' && 'bg-muted text-muted-foreground',
                            key === 'soumis' && 'bg-amber-100 text-amber-800',
                            key === 'approuve' && 'bg-emerald-100 text-emerald-800',
                            key === 'rejete' && 'bg-red-100 text-red-800',
                        ]"
                    >
                        {{ compteParStatut[key as 'brouillon' | 'soumis' | 'approuve' | 'rejete'] }}
                    </span>
                </button>
            </div>

            <!-- Liste vide -->
            <div
                v-if="projetsFiltres.length === 0"
                class="rounded-lg border border-dashed bg-muted/20 px-6 py-12 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    {{
                        filtreActif === 'soumis' ? 'Aucun musée en attente d\'approbation.'
                        : filtreActif === 'brouillon' ? 'Tous les groupes ont soumis leur musée.'
                        : 'Aucun résultat pour ce filtre.'
                    }}
                </p>
            </div>

            <!-- Cartes projets -->
            <div class="grid gap-4">
                <div
                    v-for="projet in projetsFiltres"
                    :key="projet.id"
                    class="rounded-lg border bg-background overflow-hidden"
                >
                    <div class="flex gap-4 p-4">
                        <!-- Miniature -->
                        <div class="h-20 w-28 shrink-0 overflow-hidden rounded-md bg-muted">
                            <img
                                v-if="thumbnailUrl(projet)"
                                :src="thumbnailUrl(projet)!"
                                :alt="projet.meta?.entete_titre ?? 'Aperçu'"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-[10px] text-muted-foreground"
                            >
                                <FileText class="h-5 w-5 opacity-30" />
                            </div>
                        </div>

                        <!-- Infos -->
                        <div class="flex-1 min-w-0 space-y-1.5">
                            <div class="flex items-start gap-2">
                                <p class="font-medium leading-tight">
                                    {{ projet.meta?.entete_titre ?? `Groupe ${projet.groupe.nom}` }}
                                </p>

                                <!-- Badge statut -->
                                <span
                                    :class="[
                                        'shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        projet.publication.statut === 'brouillon' && 'bg-muted text-muted-foreground',
                                        projet.publication.statut === 'soumis' && 'bg-amber-100 text-amber-800',
                                        projet.publication.statut === 'approuve' && 'bg-emerald-100 text-emerald-800',
                                        projet.publication.statut === 'rejete' && 'bg-red-100 text-red-800',
                                    ]"
                                >
                                    <FileText v-if="projet.publication.statut === 'brouillon'" class="h-2.5 w-2.5" />
                                    <Clock v-else-if="projet.publication.statut === 'soumis'" class="h-2.5 w-2.5" />
                                    <CheckCircle2 v-else-if="projet.publication.statut === 'approuve'" class="h-2.5 w-2.5" />
                                    <AlertTriangle v-else class="h-2.5 w-2.5" />
                                    {{
                                        projet.publication.statut === 'brouillon' ? 'En cours'
                                        : projet.publication.statut === 'soumis' ? 'En attente'
                                        : projet.publication.statut === 'approuve' ? 'Approuvé'
                                        : 'Rejeté'
                                    }}
                                </span>

                                <!-- Badge publié dans la galerie -->
                                <span
                                    v-if="projet.publication.est_publie"
                                    class="shrink-0 inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                >
                                    <Eye class="h-2.5 w-2.5" />
                                    Visible dans la galerie
                                </span>
                            </div>

                            <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                <Users class="h-3 w-3 shrink-0" />
                                <span class="truncate">
                                    {{ projet.groupe.membres.join(', ') }}
                                </span>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                {{ projet.groupe.classe_nom ?? `Classe ${projet.groupe.classe_id}` }}
                                <template v-if="projet.publication.soumis_le">
                                    · Soumis le {{ formatDate(projet.publication.soumis_le) }}
                                </template>
                                <template v-if="projet.publication.publie_le">
                                    · Publié le {{ formatDate(projet.publication.publie_le) }}
                                </template>
                            </p>

                            <!-- Raison du rejet (si applicable) -->
                            <p
                                v-if="projet.publication.raison_rejet"
                                class="text-xs text-red-600 dark:text-red-400 line-clamp-2"
                            >
                                Rejet : {{ projet.publication.raison_rejet }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            <!-- Voir le musée -->
                            <Link
                                :href="urlMusee(projet)"
                                class="flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <ExternalLink class="h-3 w-3" />
                                Voir
                            </Link>

                            <!-- Actions selon statut -->
                            <template v-if="projet.publication.statut === 'soumis'">
                                <button
                                    type="button"
                                    class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                                    @click="approuver(projet)"
                                >
                                    Approuver
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-red-300 px-2.5 py-1.5 text-xs text-red-600 transition-colors hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950"
                                    @click="ouvrirRejet(projet)"
                                >
                                    Rejeter…
                                </button>
                            </template>

                            <!-- Brouillon → publication directe -->
                            <template v-else-if="projet.publication.statut === 'brouillon'">
                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-md bg-primary px-2.5 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                                    @click="togglePublication(projet)"
                                >
                                    <Eye class="h-3 w-3" />
                                    Publier
                                </button>
                            </template>

                            <!-- Approuvé → bascule visibilité -->
                            <template v-else-if="projet.publication.statut === 'approuve'">
                                <button
                                    type="button"
                                    :class="[
                                        'flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors',
                                        projet.publication.est_publie
                                            ? 'border border-muted-foreground/30 text-muted-foreground hover:bg-muted'
                                            : 'bg-emerald-600 text-white hover:bg-emerald-700',
                                    ]"
                                    @click="togglePublication(projet)"
                                >
                                    <EyeOff v-if="projet.publication.est_publie" class="h-3 w-3" />
                                    <Eye v-else class="h-3 w-3" />
                                    {{ projet.publication.est_publie ? 'Masquer' : 'Republier' }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Formulaire de rejet (expandable sous la carte) -->
                    <div
                        v-if="rejetOuvertId === projet.id"
                        class="border-t bg-red-50 px-4 py-3 space-y-2 dark:bg-red-950"
                    >
                        <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                            Motif du rejet (visible par les étudiants)
                        </p>
                        <Textarea
                            v-model="raisonRejet"
                            rows="3"
                            placeholder="Décrivez ce qui doit être corrigé avant la publication…"
                            class="text-xs"
                        />
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="!raisonRejet.trim()"
                                class="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                @click="rejeter(projet)"
                            >
                                Confirmer le rejet
                            </button>
                            <button
                                type="button"
                                class="rounded border px-3 py-1.5 text-xs text-muted-foreground hover:bg-background"
                                @click="fermerRejet"
                            >
                                Annuler
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
