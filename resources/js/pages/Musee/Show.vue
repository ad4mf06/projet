<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ArrowLeft,
    BarChart2,
    BookOpen,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    GripVertical,
    ImageIcon,
    Info,
    LayoutDashboard,
    Lock,
    Minus,
    Plus,
    Trash2,
    Type,
    Video,
} from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import InputError from '@/components/InputError.vue'
import MuseeCropModal from '@/components/MuseeCropModal.vue'
import type { CropData } from '@/components/MuseeCropModal.vue'
import MuseeRichEditor from '@/components/MuseeRichEditor.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'
import {
    destroy as destroyBloc,
    reorder as reorderBlocs,
    store as storeBloc,
    update as updateBloc,
} from '@/actions/App/Http/Controllers/MuseeBlocController'
import {
    destroy as destroyImage,
    store as storeImage,
    update as updateImage,
} from '@/actions/App/Http/Controllers/MuseeImageController'
import { update as updateMeta, updateEntete } from '@/actions/App/Http/Controllers/MuseeMetaController'
import {
    destroy as destroySegment,
    store as storeSegment,
} from '@/actions/App/Http/Controllers/MuseeVideoSegmentController'

// ─── Types ────────────────────────────────────────────────────────────────────

type Cours = { id: number; nom_cours: string; code: string; groupe: string }
type Classe = { id: number; code: string; cours_id: number }
type Groupe = { id: number; code: string; classe_id: number }
type TypeProjet = { id: number; nom: string }
type Projet = {
    id: number
    titre_projet: string | null
    verrouille: boolean
    remis_le: string | null
    mode_edition_enseignant: boolean
}
type CategorieMeta = { id: number; nom: string }
type Meta = {
    id: number
    slug: string
    intro_texte: string | null
    intro_image_path: string | null
    entete_titre: string | null
    entete_sous_titre: string | null
    entete_overlay_couleur: string | null
    entete_image_position: string
    entete_image_path: string | null
    epoque: CategorieMeta | null
    thematique: CategorieMeta | null
    region: CategorieMeta | null
}
type BlocType = 'texte' | 'image' | 'separateur' | 'carrousel' | 'video'
type ImageAncree = { image_id: number | null; position: 'gauche' | 'droite' } | null
type BlocContenuTexte = { html: string; image_ancree?: ImageAncree }
type BlocContenuImage = { image_id: number | null; legende: string; alt: string }
type CarrouselItem = { image_id: number; legende: string; alt: string }
type BlocContenuCarrousel = { images: CarrouselItem[] }
type BlocContenuVideo = {
    source: 'upload' | 'youtube' | 'vimeo'
    groupe_video_id: number | null
    url_externe: string | null
    legende: string
}
type VideoSegment = {
    id: number
    section_id: number
    debut_secondes: number
    fin_secondes: number
    label: string
}
type GroupeVideo = {
    id: number
    titre: string
    url: string
    thumbnail_url: string | null
    duree: number | null
}
type Bloc = {
    id: number
    type: BlocType
    contenu: BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | null
    ordre: number
    segments: VideoSegment[]
}
type Contrainte = { type: BlocType; requis: boolean; label: string }
type Section = { id: number; label: string; ordre: number; contraintes: Contrainte[]; blocs: Bloc[] }
type MuseeImage = {
    id: number
    url: string
    alt: string
    legende: string
    crop_data?: { x: number; y: number } | null
}

type Props = {
    cours: Cours
    classe: Classe
    groupe: Groupe
    typeProjet: TypeProjet
    projet: Projet
    meta: Meta
    sections: Section[]
    images: MuseeImage[]
    template: Record<string, string>
    epoques: CategorieMeta[]
    thematiques: CategorieMeta[]
    regionsAdministratives: CategorieMeta[]
    peutEditer: boolean
    estEnseignant: boolean
    verrouille: boolean
    membres: { id: number; prenom: string; nom: string }[]
    enseignant: { id: number; prenom: string; nom: string }
    videos: GroupeVideo[]
    publication: { est_publie: boolean; publie_le: string | null }
    stats: { total: number; last7: number; parJour: Record<string, number> } | null
}

const props = defineProps<Props>()

// ─── Navigation entre panneaux ────────────────────────────────────────────────

type Panneau = 'meta' | 'entete' | 'blocs'

const panneauActif = ref<Panneau>('meta')
const sectionActiveId = ref<number | null>(null)

const panneaux = [
    { id: 'meta' as Panneau, label: 'Métadonnées', icon: Info },
    { id: 'entete' as Panneau, label: 'En-tête', icon: LayoutDashboard },
    { id: 'blocs' as Panneau, label: 'Contenu (blocs)', icon: BookOpen },
]

function selectPanneau(id: Panneau) {
    panneauActif.value = id
    if (id !== 'blocs') sectionActiveId.value = null
}

// ─── Formulaire métadonnées ───────────────────────────────────────────────────

const formMeta = useForm({
    intro_texte: props.meta.intro_texte ?? '',
    epoque_historique_id: props.meta.epoque?.id?.toString() ?? '',
    thematique_id: props.meta.thematique?.id?.toString() ?? '',
    region_administrative_id: props.meta.region?.id?.toString() ?? '',
})

function sauvegarderMeta() {
    formMeta.patch(
        updateMeta.url({
            cours: props.cours.id,
            classe: props.classe.id,
            groupe: props.groupe.id,
            typeProjet: props.typeProjet.id,
        }),
    )
}

// ─── Formulaire en-tête ───────────────────────────────────────────────────────

const formEntete = useForm({
    entete_titre: props.meta.entete_titre ?? '',
    entete_sous_titre: props.meta.entete_sous_titre ?? '',
    entete_overlay_couleur: props.meta.entete_overlay_couleur ?? '#00000040',
    entete_image_position: props.meta.entete_image_position ?? 'center',
    entete_image: null as File | null,
})

const imageEntetePreview = ref<string | null>(
    props.meta.entete_image_path ? `/storage/${props.meta.entete_image_path}` : null,
)

function onImageEnteteChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    formEntete.entete_image = file
    imageEntetePreview.value = URL.createObjectURL(file)
}

function sauvegarderEntete() {
    formEntete.post(
        updateEntete.url({
            cours: props.cours.id,
            classe: props.classe.id,
            groupe: props.groupe.id,
            typeProjet: props.typeProjet.id,
        }),
        { forceFormData: true },
    )
}

// ─── Éditeur de blocs ─────────────────────────────────────────────────────────

const sectionActive = computed(() =>
    props.sections.find((s) => s.id === sectionActiveId.value) ?? null,
)

/**
 * Retourne les contraintes obligatoires dont le type de bloc est absent de la section.
 * Utilisé pour afficher les avertissements à l'étudiant.
 */
function contraintesManquantes(section: Section): Contrainte[] {
    const typesPresents = new Set(section.blocs.map((b) => b.type))
    return (section.contraintes ?? []).filter(
        (c) => c.requis && !typesPresents.has(c.type),
    )
}

// Copie locale réactive pour le drag-and-drop — synchronisée après les réponses serveur
const localBlocs = ref<Bloc[]>([])

watch(
    sectionActive,
    (sec) => {
        localBlocs.value = sec ? sec.blocs.map((b) => ({ ...b })) : []
    },
    { immediate: true, deep: true },
)

// Bloc actuellement ouvert pour édition
const expandedBlocId = ref<number | null>(null)
const draftContenu = ref<BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | null>(null)

// Formulaire d'ajout de segment vidéo
const segmentFormBlocId = ref<number | null>(null)
const segmentDraft = ref({ section_id: '', debut_secondes: '', fin_secondes: '', label: '' })

function editerBloc(bloc: Bloc) {
    if (expandedBlocId.value === bloc.id) {
        expandedBlocId.value = null
        draftContenu.value = null
        return
    }
    expandedBlocId.value = bloc.id
    draftContenu.value = bloc.contenu ? JSON.parse(JSON.stringify(bloc.contenu)) : null
}

const routeParams = computed(() => ({
    cours: props.cours.id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
    section: sectionActiveId.value!,
}))

const imageRouteParams = computed(() => ({
    cours: props.cours.id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
}))

/** URL de la page de correction (enseignant). */
const correctionUrl = computed(() =>
    `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}/musee/correction`,
)

/** URL du toggle de publication (POST). */
const publicationUrl = computed(() =>
    `/cours/${props.cours.id}/classes/${props.classe.id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}/musee-publication`,
)

/** Publie ou dépublie le musée via une requête POST. */
function togglePublication() {
    router.post(publicationUrl.value, {}, { preserveScroll: true })
}

/** Convertit des secondes entières en mm:ss pour l'affichage dans l'éditeur. */
function formatTemps(secondes: number): string {
    const m = Math.floor(secondes / 60)
    const s = secondes % 60
    return `${m}:${s.toString().padStart(2, '0')}`
}

function ajouterSegment(bloc: Bloc) {
    router.post(
        storeSegment.url({ ...routeParams.value, bloc: bloc.id }),
        {
            section_id: Number(segmentDraft.value.section_id),
            debut_secondes: Number(segmentDraft.value.debut_secondes),
            fin_secondes: Number(segmentDraft.value.fin_secondes),
            label: segmentDraft.value.label,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                segmentDraft.value = { section_id: '', debut_secondes: '', fin_secondes: '', label: '' }
                segmentFormBlocId.value = null
            },
        },
    )
}

function supprimerSegment(segmentId: number, blocId: number) {
    if (!window.confirm('Supprimer ce segment ?')) return
    router.delete(
        destroySegment.url({ ...routeParams.value, bloc: blocId, segment: segmentId }),
        { preserveScroll: true },
    )
}

function ajouterBloc(type: BlocType) {
    router.post(storeBloc.url(routeParams.value), { type }, { preserveScroll: true })
}

function sauvegarderBloc(bloc: Bloc) {
    const data: Record<string, unknown> = {}

    if (bloc.type === 'texte') {
        const c = draftContenu.value as BlocContenuTexte
        data.html = c.html
        // Transmettre image_ancree seulement si elle a été définie dans le brouillon
        if ('image_ancree' in c) {
            data.image_ancree = c.image_ancree ?? null
        }
    } else if (bloc.type === 'image') {
        const c = draftContenu.value as BlocContenuImage
        data.image_id = c.image_id
        data.legende = c.legende
        data.alt = c.alt
    } else if (bloc.type === 'carrousel') {
        const c = draftContenu.value as BlocContenuCarrousel
        data.images = c.images
    } else if (bloc.type === 'video') {
        const c = draftContenu.value as BlocContenuVideo
        data.source = c.source
        data.groupe_video_id = c.groupe_video_id
        data.url_externe = c.url_externe
        data.legende = c.legende
    }

    router.patch(
        updateBloc.url({ ...routeParams.value, bloc: bloc.id }),
        data,
        {
            preserveScroll: true,
            onSuccess: () => {
                expandedBlocId.value = null
                draftContenu.value = null
            },
        },
    )
}

function supprimerBloc(bloc: Bloc) {
    if (!window.confirm('Supprimer ce bloc ?')) return
    router.delete(destroyBloc.url({ ...routeParams.value, bloc: bloc.id }), {
        preserveScroll: true,
        onSuccess: () => {
            if (expandedBlocId.value === bloc.id) {
                expandedBlocId.value = null
                draftContenu.value = null
            }
        },
    })
}

function onBlocDragEnd() {
    router.patch(
        reorderBlocs.url(routeParams.value),
        { ordre: localBlocs.value.map((b) => b.id) },
        { preserveScroll: true, preserveState: true },
    )
}

// ─── Bibliothèque d'images ────────────────────────────────────────────────────

const localImages = ref<MuseeImage[]>([...props.images])
const isUploadingImage = ref(false)
const cropModalData = ref<{ url: string; id: number } | null>(null)
const isSavingCrop = ref(false)

watch(
    () => props.images,
    (imgs) => {
        localImages.value = [...imgs]
    },
)

function imagePourBloc(imageId: number | null): MuseeImage | undefined {
    return localImages.value.find((img) => img.id === imageId)
}

function cropStyle(img: MuseeImage | undefined): string {
    if (!img?.crop_data) return 'object-fit: cover'
    return `object-fit: cover; object-position: ${img.crop_data.x}% ${img.crop_data.y}%`
}

// Sélectionner une image dans un bloc image standard
function selectionnerImage(imageId: number) {
    if (!draftContenu.value) return
    const img = imagePourBloc(imageId)
    ;(draftContenu.value as BlocContenuImage).image_id = imageId
    if (img && !(draftContenu.value as BlocContenuImage).alt) {
        ;(draftContenu.value as BlocContenuImage).alt = img.alt
    }
    if (img && !(draftContenu.value as BlocContenuImage).legende) {
        ;(draftContenu.value as BlocContenuImage).legende = img.legende
    }
}

// Ajouter une image au carrousel en cours d'édition
function ajouterImageCarrousel(imageId: number) {
    if (!draftContenu.value) return
    const c = draftContenu.value as BlocContenuCarrousel
    if (c.images.some((i) => i.image_id === imageId)) return // déjà présente
    const img = imagePourBloc(imageId)
    c.images.push({
        image_id: imageId,
        alt: img?.alt ?? '',
        legende: img?.legende ?? '',
    })
}

function retirerImageCarrousel(index: number) {
    if (!draftContenu.value) return
    ;(draftContenu.value as BlocContenuCarrousel).images.splice(index, 1)
}

// Sélectionner une image pour l'image ancrée d'un bloc texte
function selectionnerImageAncree(imageId: number) {
    if (!draftContenu.value) return
    const c = draftContenu.value as BlocContenuTexte
    if (!c.image_ancree) {
        c.image_ancree = { image_id: imageId, position: 'droite' }
    } else {
        c.image_ancree.image_id = imageId
    }
}

async function uploaderImage(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    isUploadingImage.value = true
    const formData = new FormData()
    formData.append('image', file)

    const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

    try {
        const response = await fetch(
            storeImage.url(imageRouteParams.value),
            {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            },
        )

        if (response.ok) {
            const img: MuseeImage = await response.json()
            localImages.value = [...localImages.value, img]
            // Ouvrir le modal de crop avant de sélectionner l'image
            cropModalData.value = { url: img.url, id: img.id }
        }
    } finally {
        isUploadingImage.value = false
        ;(event.target as HTMLInputElement).value = ''
    }
}

async function confirmerCrop(cropData: CropData) {
    if (!cropModalData.value) return
    isSavingCrop.value = true

    const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    const imageId = cropModalData.value.id

    try {
        const response = await fetch(
            updateImage.url({ ...imageRouteParams.value, museeImage: imageId }),
            {
                method: 'PATCH',
                body: JSON.stringify({ crop_data: cropData }),
                headers: {
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            },
        )

        if (response.ok) {
            const updated: MuseeImage = await response.json()
            localImages.value = localImages.value.map((img) =>
                img.id === updated.id ? updated : img,
            )
        }
    } finally {
        isSavingCrop.value = false
        // Sélectionner l'image (dans le bloc en cours d'édition) après le crop
        if (draftContenu.value) {
            const type = localBlocs.value.find((b) => b.id === expandedBlocId.value)?.type
            if (type === 'image') {
                selectionnerImage(imageId)
            } else if (type === 'carrousel') {
                ajouterImageCarrousel(imageId)
            }
        }
        cropModalData.value = null
    }
}

function annulerCrop() {
    // L'image est déjà uploadée — on la sélectionne sans crop
    if (cropModalData.value && draftContenu.value) {
        const type = localBlocs.value.find((b) => b.id === expandedBlocId.value)?.type
        if (type === 'image') {
            selectionnerImage(cropModalData.value.id)
        } else if (type === 'carrousel') {
            ajouterImageCarrousel(cropModalData.value.id)
        }
    }
    cropModalData.value = null
}

// ─── Indicateurs de complétion ────────────────────────────────────────────────

const metaComplete = computed(
    () => formMeta.intro_texte.trim() !== '' && !!formMeta.epoque_historique_id,
)

const enteteComplete = computed(() => formEntete.entete_titre.trim() !== '')

const nbBlocsTotal = computed(() =>
    props.sections.reduce((acc, s) => acc + s.blocs.length, 0),
)

// ─── CSS variables du template ────────────────────────────────────────────────

const templateStyle = computed(() => props.template)
</script>

<template>
    <AppLayout>
        <Head :title="typeProjet.nom" />

        <!-- Modal de crop (affiché par-dessus tout) -->
        <MuseeCropModal
            v-if="cropModalData"
            :image-url="cropModalData.url"
            :image-id="cropModalData.id"
            @confirmed="confirmerCrop"
            @cancelled="annulerCrop"
        />

        <!-- Bannière verrouillé -->
        <div
            v-if="verrouille"
            class="flex items-center gap-2 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            <Lock class="h-3.5 w-3.5" />
            Ce projet est verrouillé — la modification est désactivée.
        </div>

        <div class="flex h-[calc(100vh-3.5rem)] overflow-hidden">
            <!-- ─── Panneau gauche — navigation ────────────────────────────────── -->
            <aside
                class="flex w-60 shrink-0 flex-col border-r bg-background"
                :style="templateStyle"
            >
                <!-- En-tête sidebar -->
                <div class="border-b px-4 py-3">
                    <Link
                        :href="`/cours/${cours.id}/classes/${classe.id}/groupes/${groupe.id}/projets`"
                        class="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft class="h-3 w-3" />
                        Retour aux projets
                    </Link>
                    <p class="text-xs font-semibold text-foreground">{{ typeProjet.nom }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Groupe {{ groupe.code }}
                    </p>
                </div>

                <!-- Navigation des panneaux -->
                <nav class="flex-1 overflow-y-auto px-2 py-3">
                    <ul class="space-y-0.5">
                        <li v-for="panneau in panneaux" :key="panneau.id">
                            <button
                                type="button"
                                :class="[
                                    'flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors',
                                    panneauActif === panneau.id
                                        ? 'bg-primary/10 font-medium text-primary'
                                        : 'text-foreground hover:bg-muted',
                                ]"
                                @click="selectPanneau(panneau.id)"
                            >
                                <component :is="panneau.icon" class="h-4 w-4 shrink-0" />
                                <span class="flex-1">{{ panneau.label }}</span>
                                <!-- Indicateurs de complétion -->
                                <CheckCircle2
                                    v-if="panneau.id === 'meta' && metaComplete"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <CheckCircle2
                                    v-else-if="panneau.id === 'entete' && enteteComplete"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <span
                                    v-else-if="panneau.id === 'blocs' && nbBlocsTotal > 0"
                                    class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary"
                                >{{ nbBlocsTotal }}</span>
                            </button>

                            <!-- Sous-nav sections (visible quand blocs est actif) -->
                            <template v-if="panneau.id === 'blocs' && panneauActif === 'blocs'">
                                <ul class="mt-0.5 space-y-0.5 pl-5">
                                    <li
                                        v-if="sections.length === 0"
                                        class="px-3 py-1 text-xs italic text-muted-foreground"
                                    >
                                        Aucune section définie
                                    </li>
                                    <li v-for="section in sections" :key="section.id">
                                        <button
                                            type="button"
                                            :class="[
                                                'flex w-full items-center gap-2 rounded px-3 py-1.5 text-left text-xs transition-colors',
                                                sectionActiveId === section.id
                                                    ? 'bg-primary/10 font-medium text-primary'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                            ]"
                                            @click="sectionActiveId = section.id"
                                        >
                                            <span class="flex-1 truncate">{{ section.label }}</span>
                                            <span class="shrink-0 text-[10px]">
                                                {{ section.blocs.length }}
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                            </template>
                        </li>
                    </ul>
                </nav>

                <!-- Membres -->
                <div class="border-t px-4 py-3">
                    <p class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Membres
                    </p>
                    <ul class="space-y-0.5">
                        <li
                            v-for="membre in membres"
                            :key="membre.id"
                            class="text-xs text-muted-foreground"
                        >
                            {{ membre.prenom }} {{ membre.nom }}
                        </li>
                    </ul>
                </div>

                <!-- Contrôles enseignant -->
                <div
                    v-if="estEnseignant"
                    class="border-t px-4 py-3 space-y-1"
                >
                    <p class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Enseignant
                    </p>

                    <Link
                        :href="correctionUrl"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <BookOpen class="h-3.5 w-3.5 shrink-0" />
                        Page de correction
                    </Link>

                    <button
                        type="button"
                        :class="[
                            'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors',
                            publication.est_publie
                                ? 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        ]"
                        @click="togglePublication"
                    >
                        <CheckCircle2
                            v-if="publication.est_publie"
                            class="h-3.5 w-3.5 shrink-0"
                        />
                        <Lock
                            v-else
                            class="h-3.5 w-3.5 shrink-0"
                        />
                        {{ publication.est_publie ? 'Publié dans la galerie' : 'Publier dans la galerie' }}
                    </button>

                    <!-- Mini stats de vues -->
                    <div
                        v-if="stats !== null"
                        class="mt-2 rounded-md border px-3 py-2"
                    >
                        <p class="mb-1.5 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            <BarChart2 class="h-3 w-3" />
                            Statistiques de vues
                        </p>
                        <div class="flex items-baseline justify-between text-xs">
                            <span class="text-muted-foreground">Total</span>
                            <span class="font-semibold tabular-nums">{{ stats.total }}</span>
                        </div>
                        <div class="flex items-baseline justify-between text-xs">
                            <span class="text-muted-foreground">7 derniers jours</span>
                            <span class="font-semibold tabular-nums">{{ stats.last7 }}</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ─── Panneau droit — éditeur ────────────────────────────────────── -->
            <main class="flex-1 overflow-y-auto bg-muted/30 p-6">

                <!-- ── Panneau : Métadonnées ── -->
                <div v-if="panneauActif === 'meta'" class="mx-auto max-w-xl space-y-6">
                    <div>
                        <h2 class="text-base font-semibold">Métadonnées du projet</h2>
                        <p class="text-sm text-muted-foreground">
                            Ces informations apparaîtront sur la carte galerie publique.
                        </p>
                    </div>

                    <Card>
                        <CardContent class="grid gap-5 pt-6">
                            <!-- Intro texte -->
                            <div class="grid gap-2">
                                <Label for="intro_texte">Texte d'introduction</Label>
                                <Textarea
                                    id="intro_texte"
                                    v-model="formMeta.intro_texte"
                                    :disabled="!peutEditer"
                                    rows="3"
                                    placeholder="Courte description visible dans la galerie…"
                                />
                                <InputError :message="formMeta.errors.intro_texte" />
                            </div>

                            <!-- Catégorisation -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <!-- Époque -->
                                <div class="grid gap-2">
                                    <Label>Époque historique</Label>
                                    <Select
                                        v-model="formMeta.epoque_historique_id"
                                        :disabled="!peutEditer"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choisir…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="epoque in epoques"
                                                :key="epoque.id"
                                                :value="epoque.id.toString()"
                                            >
                                                {{ epoque.nom }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="formMeta.errors.epoque_historique_id" />
                                </div>

                                <!-- Thématique -->
                                <div class="grid gap-2">
                                    <Label>Thématique</Label>
                                    <Select
                                        v-model="formMeta.thematique_id"
                                        :disabled="!peutEditer"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choisir…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="thematique in thematiques"
                                                :key="thematique.id"
                                                :value="thematique.id.toString()"
                                            >
                                                {{ thematique.nom }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="formMeta.errors.thematique_id" />
                                </div>

                                <!-- Région administrative -->
                                <div class="grid gap-2">
                                    <Label>Région administrative</Label>
                                    <Select
                                        v-model="formMeta.region_administrative_id"
                                        :disabled="!peutEditer"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choisir…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="region in regionsAdministratives"
                                                :key="region.id"
                                                :value="region.id.toString()"
                                            >
                                                {{ region.nom }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="formMeta.errors.region_administrative_id" />
                                </div>
                            </div>

                            <!-- Slug (lecture seule) -->
                            <div class="grid gap-1.5">
                                <Label class="text-muted-foreground">Identifiant unique (slug)</Label>
                                <p class="rounded border bg-muted/50 px-3 py-2 font-mono text-xs text-muted-foreground">
                                    {{ meta.slug }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end">
                        <Button
                            :disabled="!peutEditer || formMeta.processing"
                            @click="sauvegarderMeta"
                        >
                            {{ formMeta.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <!-- ── Panneau : En-tête ── -->
                <div v-else-if="panneauActif === 'entete'" class="mx-auto max-w-xl space-y-6">
                    <div>
                        <h2 class="text-base font-semibold">En-tête du musée</h2>
                        <p class="text-sm text-muted-foreground">
                            Titre, sous-titre, image de fond et position de cadrage.
                        </p>
                    </div>

                    <!-- Prévisualisation de l'en-tête -->
                    <div
                        class="relative flex h-40 items-end overflow-hidden rounded-lg border"
                        :style="{
                            backgroundImage: imageEntetePreview
                                ? `url(${imageEntetePreview})`
                                : undefined,
                            backgroundSize: 'cover',
                            backgroundPosition: formEntete.entete_image_position === 'top'
                                ? 'center top'
                                : formEntete.entete_image_position === 'bottom'
                                  ? 'center bottom'
                                  : 'center center',
                            backgroundColor: imageEntetePreview
                                ? undefined
                                : 'hsl(var(--muted))',
                        }"
                    >
                        <div
                            class="absolute inset-0"
                            :style="{
                                backgroundColor:
                                    formEntete.entete_overlay_couleur || 'transparent',
                                opacity: imageEntetePreview ? 0.5 : 0,
                            }"
                        />
                        <div
                            v-if="!imageEntetePreview"
                            class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
                        >
                            <ImageIcon class="mr-2 h-5 w-5" />
                            Aucune image sélectionnée
                        </div>
                        <div
                            v-if="formEntete.entete_titre || formEntete.entete_sous_titre"
                            class="relative z-10 p-4"
                        >
                            <p
                                v-if="formEntete.entete_titre"
                                class="text-lg font-bold leading-tight text-white drop-shadow"
                            >
                                {{ formEntete.entete_titre }}
                            </p>
                            <p
                                v-if="formEntete.entete_sous_titre"
                                class="text-sm text-white/90 drop-shadow"
                            >
                                {{ formEntete.entete_sous_titre }}
                            </p>
                        </div>
                    </div>

                    <Card>
                        <CardContent class="grid gap-5 pt-6">
                            <div class="grid gap-2">
                                <Label for="entete_image">Image de fond</Label>
                                <Input
                                    id="entete_image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :disabled="!peutEditer"
                                    class="cursor-pointer"
                                    @change="onImageEnteteChange"
                                />
                                <p class="text-xs text-muted-foreground">
                                    JPEG, PNG ou WebP — max 4 Mo.
                                </p>
                                <InputError :message="formEntete.errors.entete_image" />
                            </div>

                            <!-- Position de l'image de fond (tâche 4.4) -->
                            <div class="grid gap-2">
                                <Label>Position de l'image</Label>
                                <div class="flex gap-2">
                                    <button
                                        v-for="opt in [
                                            { val: 'top', label: 'Haut' },
                                            { val: 'center', label: 'Centre' },
                                            { val: 'bottom', label: 'Bas' },
                                        ]"
                                        :key="opt.val"
                                        type="button"
                                        :disabled="!peutEditer"
                                        :class="[
                                            'flex-1 rounded border py-1.5 text-sm transition-colors',
                                            formEntete.entete_image_position === opt.val
                                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                                : 'border-border text-muted-foreground hover:bg-muted',
                                        ]"
                                        @click="formEntete.entete_image_position = opt.val"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Contrôle quelle partie de l'image reste visible quand elle est rognée.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="entete_titre">Titre de l'en-tête</Label>
                                <Input
                                    id="entete_titre"
                                    v-model="formEntete.entete_titre"
                                    :disabled="!peutEditer"
                                    placeholder="Ex : Le Musée de la Nouvelle-France"
                                />
                                <InputError :message="formEntete.errors.entete_titre" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="entete_sous_titre">Sous-titre</Label>
                                <Input
                                    id="entete_sous_titre"
                                    v-model="formEntete.entete_sous_titre"
                                    :disabled="!peutEditer"
                                    placeholder="Ex : Groupe 3 — Histoire 330"
                                />
                                <InputError :message="formEntete.errors.entete_sous_titre" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="entete_overlay">Couleur de l'overlay</Label>
                                <div class="flex items-center gap-3">
                                    <input
                                        id="entete_overlay"
                                        v-model="formEntete.entete_overlay_couleur"
                                        type="color"
                                        :disabled="!peutEditer"
                                        class="h-9 w-14 cursor-pointer rounded border border-input"
                                    />
                                    <span class="font-mono text-sm text-muted-foreground">
                                        {{ formEntete.entete_overlay_couleur }}
                                    </span>
                                </div>
                                <InputError :message="formEntete.errors.entete_overlay_couleur" />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end">
                        <Button
                            :disabled="!peutEditer || formEntete.processing"
                            @click="sauvegarderEntete"
                        >
                            {{ formEntete.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <!-- ── Panneau : Blocs ── -->
                <div v-else-if="panneauActif === 'blocs'" class="mx-auto max-w-2xl space-y-4">

                    <!-- Sélecteur de section (aucune section active) -->
                    <template v-if="!sectionActiveId">
                        <div>
                            <h2 class="text-base font-semibold">Contenu par section</h2>
                            <p class="text-sm text-muted-foreground">
                                Sélectionnez une section pour ajouter et organiser les blocs.
                            </p>
                        </div>

                        <div v-if="sections.length === 0">
                            <Card>
                                <CardContent class="py-10 text-center text-sm text-muted-foreground">
                                    Aucune section n'a encore été définie par l'enseignant pour ce
                                    type de projet.
                                </CardContent>
                            </Card>
                        </div>

                        <div v-else class="grid gap-3">
                            <Card
                                v-for="section in sections"
                                :key="section.id"
                                class="cursor-pointer transition-colors hover:bg-muted/60"
                                @click="sectionActiveId = section.id"
                            >
                                <CardContent class="flex items-center justify-between py-4">
                                    <div class="flex items-center gap-3">
                                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-medium">{{ section.label }}</span>
                                        <!-- Indicateur de blocs obligatoires manquants -->
                                        <span
                                            v-if="contraintesManquantes(section).length > 0"
                                            class="flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                            :title="`${contraintesManquantes(section).length} bloc(s) obligatoire(s) manquant(s)`"
                                        >
                                            <AlertTriangle class="h-2.5 w-2.5" />
                                            {{ contraintesManquantes(section).length }}
                                        </span>
                                    </div>
                                    <span class="text-sm text-muted-foreground">
                                        {{ section.blocs.length }}
                                        {{ section.blocs.length === 1 ? 'bloc' : 'blocs' }}
                                    </span>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- Éditeur de blocs d'une section -->
                    <template v-else>
                        <!-- En-tête section -->
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                @click="sectionActiveId = null"
                            >
                                <ArrowLeft class="h-3.5 w-3.5" />
                                Sections
                            </button>
                            <span class="text-xs text-muted-foreground">/</span>
                            <h2 class="text-sm font-semibold">{{ sectionActive?.label }}</h2>
                        </div>

                        <!-- Avertissement : blocs obligatoires manquants -->
                        <div
                            v-if="sectionActive && contraintesManquantes(sectionActive).length > 0"
                            class="flex flex-col gap-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
                        >
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-amber-800">
                                <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                                Blocs obligatoires manquants
                            </p>
                            <ul class="ml-5 list-disc text-xs text-amber-700">
                                <li
                                    v-for="c in contraintesManquantes(sectionActive)"
                                    :key="c.label + c.type"
                                >
                                    {{ c.label || c.type }}
                                </li>
                            </ul>
                        </div>

                        <!-- Liste des blocs (draggable) -->
                        <VueDraggable
                            v-model="localBlocs"
                            handle=".bloc-drag"
                            :animation="150"
                            class="space-y-3"
                            @end="onBlocDragEnd"
                        >
                            <div
                                v-for="bloc in localBlocs"
                                :key="bloc.id"
                                class="rounded-lg border bg-background shadow-sm"
                            >
                                <!-- En-tête du bloc -->
                                <div class="flex items-center gap-2 border-b px-3 py-2">
                                    <GripVertical
                                        class="bloc-drag h-4 w-4 shrink-0 cursor-grab text-muted-foreground/40"
                                    />

                                    <!-- Badge type -->
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            bloc.type === 'texte'
                                                ? 'bg-blue-100 text-blue-700'
                                                : bloc.type === 'image'
                                                  ? 'bg-emerald-100 text-emerald-700'
                                                  : bloc.type === 'carrousel'
                                                    ? 'bg-violet-100 text-violet-700'
                                                    : bloc.type === 'video'
                                                      ? 'bg-amber-100 text-amber-700'
                                                      : 'bg-gray-100 text-gray-600',
                                        ]"
                                    >
                                        <Type v-if="bloc.type === 'texte'" class="h-3 w-3" />
                                        <ImageIcon v-else-if="bloc.type === 'image'" class="h-3 w-3" />
                                        <ChevronRight v-else-if="bloc.type === 'carrousel'" class="h-3 w-3" />
                                        <Video v-else-if="bloc.type === 'video'" class="h-3 w-3" />
                                        <Minus v-else class="h-3 w-3" />
                                        {{ bloc.type }}
                                    </span>

                                    <span class="flex-1" />

                                    <!-- Bouton éditer (sauf séparateur) -->
                                    <button
                                        v-if="bloc.type !== 'separateur' && peutEditer"
                                        type="button"
                                        :class="[
                                            'rounded px-2 py-1 text-xs transition-colors',
                                            expandedBlocId === bloc.id
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted',
                                        ]"
                                        @click="editerBloc(bloc)"
                                    >
                                        {{ expandedBlocId === bloc.id ? 'Fermer' : 'Éditer' }}
                                    </button>

                                    <!-- Bouton supprimer -->
                                    <button
                                        v-if="peutEditer"
                                        type="button"
                                        class="rounded p-1 text-muted-foreground hover:bg-red-50 hover:text-red-600"
                                        title="Supprimer ce bloc"
                                        @click="supprimerBloc(bloc)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Aperçu condensé (quand fermé) -->
                                <div
                                    v-if="expandedBlocId !== bloc.id"
                                    class="px-3 py-2"
                                >
                                    <!-- Aperçu texte -->
                                    <p
                                        v-if="bloc.type === 'texte'"
                                        class="line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ bloc.contenu && (bloc.contenu as BlocContenuTexte).html
                                            ? (bloc.contenu as BlocContenuTexte).html.replace(/<[^>]*>/g, ' ').trim() || '(vide)'
                                            : '(vide)' }}
                                    </p>

                                    <!-- Aperçu image -->
                                    <div
                                        v-else-if="bloc.type === 'image'"
                                        class="flex items-center gap-2"
                                    >
                                        <img
                                            v-if="bloc.contenu && (bloc.contenu as BlocContenuImage).image_id && imagePourBloc((bloc.contenu as BlocContenuImage).image_id!)"
                                            :src="imagePourBloc((bloc.contenu as BlocContenuImage).image_id!)!.url"
                                            :alt="(bloc.contenu as BlocContenuImage).alt"
                                            class="h-10 w-14 rounded object-cover"
                                        />
                                        <span
                                            v-else
                                            class="text-xs italic text-muted-foreground"
                                        >Aucune image sélectionnée</span>
                                    </div>

                                    <!-- Aperçu carrousel -->
                                    <div
                                        v-else-if="bloc.type === 'carrousel'"
                                        class="flex items-center gap-1.5"
                                    >
                                        <div class="flex -space-x-2">
                                            <img
                                                v-for="(item, i) in (bloc.contenu as BlocContenuCarrousel).images.slice(0, 4)"
                                                :key="i"
                                                :src="imagePourBloc(item.image_id)?.url"
                                                class="h-8 w-8 rounded-full border-2 border-white object-cover"
                                                :alt="item.alt"
                                            />
                                        </div>
                                        <span class="text-xs text-muted-foreground">
                                            {{ (bloc.contenu as BlocContenuCarrousel).images.length }}
                                            image{{ (bloc.contenu as BlocContenuCarrousel).images.length !== 1 ? 's' : '' }}
                                        </span>
                                    </div>

                                    <!-- Aperçu vidéo -->
                                    <div
                                        v-else-if="bloc.type === 'video'"
                                        class="flex items-center gap-2"
                                    >
                                        <div class="flex h-8 w-12 shrink-0 items-center justify-center rounded bg-muted">
                                            <Video class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <span class="truncate text-xs text-muted-foreground">
                                            {{ (bloc.contenu as BlocContenuVideo)?.source === 'upload'
                                                ? (props.videos.find(v => v.id === (bloc.contenu as BlocContenuVideo)?.groupe_video_id)?.titre ?? 'Vidéo non sélectionnée')
                                                : ((bloc.contenu as BlocContenuVideo)?.url_externe || 'URL non définie') }}
                                        </span>
                                    </div>

                                    <!-- Séparateur -->
                                    <div
                                        v-else
                                        class="h-px w-full bg-border"
                                    />
                                </div>

                                <!-- Éditeur inline (quand ouvert) -->
                                <div
                                    v-if="expandedBlocId === bloc.id && draftContenu !== null"
                                    class="space-y-3 p-3"
                                >
                                    <!-- Éditeur texte -->
                                    <template v-if="bloc.type === 'texte'">
                                        <MuseeRichEditor
                                            v-model="(draftContenu as BlocContenuTexte).html"
                                            :readonly="!peutEditer"
                                        />

                                        <!-- ── Image ancrée (tâche 4.3) ── -->
                                        <div class="rounded border bg-muted/30 p-3 space-y-2">
                                            <p class="text-xs font-medium text-muted-foreground">
                                                Image latérale (optionnelle)
                                            </p>

                                            <!-- Image ancrée sélectionnée -->
                                            <div
                                                v-if="(draftContenu as BlocContenuTexte).image_ancree?.image_id"
                                                class="flex items-center gap-3"
                                            >
                                                <img
                                                    :src="imagePourBloc((draftContenu as BlocContenuTexte).image_ancree!.image_id!)?.url"
                                                    class="h-14 w-20 rounded object-cover"
                                                    alt=""
                                                />
                                                <div class="space-y-1.5">
                                                    <!-- Position gauche / droite -->
                                                    <div class="flex gap-1.5">
                                                        <button
                                                            v-for="pos in [{ val: 'gauche', label: 'Gauche' }, { val: 'droite', label: 'Droite' }]"
                                                            :key="pos.val"
                                                            type="button"
                                                            :class="[
                                                                'rounded border px-2 py-0.5 text-xs',
                                                                (draftContenu as BlocContenuTexte).image_ancree!.position === pos.val
                                                                    ? 'border-primary bg-primary/10 text-primary'
                                                                    : 'border-border text-muted-foreground hover:bg-muted',
                                                            ]"
                                                            @click="(draftContenu as BlocContenuTexte).image_ancree!.position = pos.val as 'gauche' | 'droite'"
                                                        >
                                                            {{ pos.label }}
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="text-xs text-red-500 hover:underline"
                                                        @click="(draftContenu as BlocContenuTexte).image_ancree = null"
                                                    >
                                                        Retirer l'image
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Grille de sélection -->
                                            <div v-if="localImages.length > 0 && !(draftContenu as BlocContenuTexte).image_ancree?.image_id">
                                                <p class="mb-1.5 text-[11px] text-muted-foreground">
                                                    Choisir une image :
                                                </p>
                                                <div class="grid grid-cols-6 gap-1.5">
                                                    <button
                                                        v-for="img in localImages"
                                                        :key="img.id"
                                                        type="button"
                                                        class="overflow-hidden rounded border-2 border-transparent hover:border-primary/40"
                                                        :title="img.alt || img.legende"
                                                        @click="selectionnerImageAncree(img.id)"
                                                    >
                                                        <img
                                                            :src="img.url"
                                                            :alt="img.alt"
                                                            class="h-10 w-full object-cover"
                                                            :style="cropStyle(img)"
                                                        />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Éditeur image -->
                                    <template v-else-if="bloc.type === 'image'">
                                        <!-- Image sélectionnée -->
                                        <div
                                            v-if="(draftContenu as BlocContenuImage).image_id && imagePourBloc((draftContenu as BlocContenuImage).image_id!)"
                                            class="flex items-start gap-3 rounded border p-2"
                                        >
                                            <img
                                                :src="imagePourBloc((draftContenu as BlocContenuImage).image_id!)!.url"
                                                :alt="(draftContenu as BlocContenuImage).alt"
                                                class="h-16 w-24 rounded object-cover"
                                                :style="cropStyle(imagePourBloc((draftContenu as BlocContenuImage).image_id!))"
                                            />
                                            <div class="flex-1 space-y-1">
                                                <p class="text-xs font-medium">Image sélectionnée</p>
                                                <button
                                                    type="button"
                                                    class="text-xs text-red-500 hover:underline"
                                                    @click="(draftContenu as BlocContenuImage).image_id = null"
                                                >
                                                    Retirer
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Bibliothèque d'images -->
                                        <div v-if="localImages.length > 0">
                                            <p class="mb-2 text-xs font-medium text-muted-foreground">
                                                Choisir depuis la bibliothèque :
                                            </p>
                                            <div class="grid grid-cols-4 gap-2">
                                                <button
                                                    v-for="img in localImages"
                                                    :key="img.id"
                                                    type="button"
                                                    :class="[
                                                        'overflow-hidden rounded border-2 transition-colors',
                                                        (draftContenu as BlocContenuImage).image_id === img.id
                                                            ? 'border-primary'
                                                            : 'border-transparent hover:border-muted-foreground/40',
                                                    ]"
                                                    :title="img.alt || img.legende"
                                                    @click="selectionnerImage(img.id)"
                                                >
                                                    <img
                                                        :src="img.url"
                                                        :alt="img.alt"
                                                        class="h-16 w-full object-cover"
                                                        :style="cropStyle(img)"
                                                    />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload nouvelle image -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Ou importer une image</Label>
                                            <Input
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp,image/gif"
                                                :disabled="isUploadingImage || !peutEditer"
                                                class="cursor-pointer text-xs"
                                                @change="uploaderImage"
                                            />
                                            <p v-if="isUploadingImage" class="text-xs text-muted-foreground">
                                                Envoi en cours…
                                            </p>
                                        </div>

                                        <!-- Alt et légende -->
                                        <div class="grid gap-3">
                                            <div class="grid gap-1.5">
                                                <Label for="bloc-alt" class="text-xs">Texte alternatif (alt)</Label>
                                                <Input
                                                    id="bloc-alt"
                                                    v-model="(draftContenu as BlocContenuImage).alt"
                                                    :disabled="!peutEditer"
                                                    class="text-xs"
                                                    placeholder="Description de l'image pour l'accessibilité"
                                                />
                                            </div>
                                            <div class="grid gap-1.5">
                                                <Label for="bloc-legende" class="text-xs">Légende</Label>
                                                <Input
                                                    id="bloc-legende"
                                                    v-model="(draftContenu as BlocContenuImage).legende"
                                                    :disabled="!peutEditer"
                                                    class="text-xs"
                                                    placeholder="Légende affichée sous l'image"
                                                />
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Éditeur carrousel (tâche 4.2) -->
                                    <template v-else-if="bloc.type === 'carrousel'">
                                        <!-- Images dans le carrousel -->
                                        <div v-if="(draftContenu as BlocContenuCarrousel).images.length > 0" class="space-y-2">
                                            <p class="text-xs font-medium text-muted-foreground">
                                                Images du carrousel :
                                            </p>
                                            <VueDraggable
                                                v-model="(draftContenu as BlocContenuCarrousel).images"
                                                handle=".carr-drag"
                                                :animation="150"
                                                class="space-y-1.5"
                                            >
                                                <div
                                                    v-for="(item, idx) in (draftContenu as BlocContenuCarrousel).images"
                                                    :key="item.image_id"
                                                    class="flex items-center gap-2 rounded border bg-muted/20 p-2"
                                                >
                                                    <GripVertical class="carr-drag h-4 w-4 shrink-0 cursor-grab text-muted-foreground/40" />
                                                    <img
                                                        :src="imagePourBloc(item.image_id)?.url"
                                                        class="h-10 w-14 rounded object-cover"
                                                        :style="cropStyle(imagePourBloc(item.image_id))"
                                                        :alt="item.alt"
                                                    />
                                                    <div class="flex-1 grid gap-1">
                                                        <input
                                                            v-model="item.alt"
                                                            class="w-full rounded border border-input bg-background px-2 py-0.5 text-xs"
                                                            placeholder="Texte alt…"
                                                        />
                                                        <input
                                                            v-model="item.legende"
                                                            class="w-full rounded border border-input bg-background px-2 py-0.5 text-xs"
                                                            placeholder="Légende…"
                                                        />
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="rounded p-1 text-muted-foreground hover:bg-red-50 hover:text-red-500"
                                                        @click="retirerImageCarrousel(idx)"
                                                    >
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </VueDraggable>
                                        </div>

                                        <!-- Bibliothèque — ajouter au carrousel -->
                                        <div v-if="localImages.length > 0">
                                            <p class="mb-1.5 text-xs font-medium text-muted-foreground">
                                                Ajouter depuis la bibliothèque :
                                            </p>
                                            <div class="grid grid-cols-5 gap-1.5">
                                                <button
                                                    v-for="img in localImages"
                                                    :key="img.id"
                                                    type="button"
                                                    :disabled="(draftContenu as BlocContenuCarrousel).images.some(i => i.image_id === img.id)"
                                                    :class="[
                                                        'overflow-hidden rounded border-2 transition-colors disabled:opacity-40',
                                                        (draftContenu as BlocContenuCarrousel).images.some(i => i.image_id === img.id)
                                                            ? 'border-primary cursor-default'
                                                            : 'border-transparent hover:border-primary/40',
                                                    ]"
                                                    :title="img.alt || img.legende"
                                                    @click="ajouterImageCarrousel(img.id)"
                                                >
                                                    <img
                                                        :src="img.url"
                                                        :alt="img.alt"
                                                        class="h-12 w-full object-cover"
                                                        :style="cropStyle(img)"
                                                    />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload nouvelle image pour le carrousel -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Ou importer une image</Label>
                                            <Input
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp,image/gif"
                                                :disabled="isUploadingImage || !peutEditer"
                                                class="cursor-pointer text-xs"
                                                @change="uploaderImage"
                                            />
                                            <p v-if="isUploadingImage" class="text-xs text-muted-foreground">
                                                Envoi en cours…
                                            </p>
                                        </div>
                                    </template>

                                    <!-- Éditeur vidéo -->
                                    <template v-else-if="bloc.type === 'video'">
                                        <!-- Source -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Source</Label>
                                            <div class="flex gap-2">
                                                <button
                                                    v-for="src in [
                                                        { val: 'upload', label: 'Vidéo du groupe' },
                                                        { val: 'youtube', label: 'YouTube' },
                                                        { val: 'vimeo', label: 'Vimeo' },
                                                    ]"
                                                    :key="src.val"
                                                    type="button"
                                                    :class="[
                                                        'flex-1 rounded border py-1.5 text-xs transition-colors',
                                                        (draftContenu as BlocContenuVideo).source === src.val
                                                            ? 'border-primary bg-primary/10 font-medium text-primary'
                                                            : 'border-border text-muted-foreground hover:bg-muted',
                                                    ]"
                                                    @click="(draftContenu as BlocContenuVideo).source = src.val as 'upload' | 'youtube' | 'vimeo'"
                                                >
                                                    {{ src.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload : sélecteur vidéo du groupe -->
                                        <div v-if="(draftContenu as BlocContenuVideo).source === 'upload'">
                                            <p v-if="props.videos.length === 0" class="text-xs italic text-muted-foreground">
                                                Aucune vidéo disponible dans ce groupe.
                                            </p>
                                            <div v-else class="grid grid-cols-2 gap-2">
                                                <button
                                                    v-for="vid in props.videos"
                                                    :key="vid.id"
                                                    type="button"
                                                    :class="[
                                                        'flex items-start gap-2 rounded border p-2 text-left transition-colors',
                                                        (draftContenu as BlocContenuVideo).groupe_video_id === vid.id
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border hover:bg-muted',
                                                    ]"
                                                    @click="(draftContenu as BlocContenuVideo).groupe_video_id = vid.id"
                                                >
                                                    <img
                                                        v-if="vid.thumbnail_url"
                                                        :src="vid.thumbnail_url"
                                                        class="h-12 w-20 shrink-0 rounded object-cover"
                                                        alt=""
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex h-12 w-20 shrink-0 items-center justify-center rounded bg-muted"
                                                    >
                                                        <Video class="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                    <span class="line-clamp-2 text-xs">{{ vid.titre }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- YouTube / Vimeo : URL -->
                                        <div v-else class="grid gap-1.5">
                                            <Label class="text-xs">URL de la vidéo</Label>
                                            <Input
                                                v-model="(draftContenu as BlocContenuVideo).url_externe"
                                                :disabled="!peutEditer"
                                                class="text-xs"
                                                :placeholder="(draftContenu as BlocContenuVideo).source === 'youtube'
                                                    ? 'https://www.youtube.com/watch?v=…'
                                                    : 'https://vimeo.com/…'"
                                            />
                                        </div>

                                        <!-- Légende -->
                                        <div class="grid gap-1.5">
                                            <Label class="text-xs">Légende (optionnelle)</Label>
                                            <Input
                                                v-model="(draftContenu as BlocContenuVideo).legende"
                                                :disabled="!peutEditer"
                                                class="text-xs"
                                                placeholder="Légende affichée sous la vidéo"
                                            />
                                        </div>

                                        <!-- Segments / Chapitres -->
                                        <div class="space-y-2 rounded border bg-muted/20 p-3">
                                            <p class="text-xs font-medium text-muted-foreground">
                                                Segments (chapitres)
                                            </p>

                                            <!-- Liste des segments existants -->
                                            <div v-if="bloc.segments && bloc.segments.length > 0" class="space-y-1.5">
                                                <div
                                                    v-for="seg in bloc.segments"
                                                    :key="seg.id"
                                                    class="flex items-center gap-2 rounded border bg-background px-2 py-1.5"
                                                >
                                                    <span class="font-mono text-xs text-muted-foreground">
                                                        {{ formatTemps(seg.debut_secondes) }}–{{ formatTemps(seg.fin_secondes) }}
                                                    </span>
                                                    <span class="flex-1 text-xs">{{ seg.label }}</span>
                                                    <button
                                                        type="button"
                                                        class="rounded p-0.5 text-muted-foreground hover:bg-red-50 hover:text-red-500"
                                                        :title="`Supprimer le segment « ${seg.label} »`"
                                                        @click="supprimerSegment(seg.id, bloc.id)"
                                                    >
                                                        <Trash2 class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </div>
                                            <p v-else class="text-[11px] italic text-muted-foreground">
                                                Aucun segment défini.
                                            </p>

                                            <!-- Formulaire d'ajout de segment -->
                                            <template v-if="segmentFormBlocId === bloc.id">
                                                <div class="grid gap-2 rounded border border-dashed bg-background p-2">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div class="grid gap-1">
                                                            <Label class="text-[11px]">Début (s)</Label>
                                                            <Input
                                                                v-model="segmentDraft.debut_secondes"
                                                                type="number"
                                                                min="0"
                                                                class="text-xs"
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <Label class="text-[11px]">Fin (s)</Label>
                                                            <Input
                                                                v-model="segmentDraft.fin_secondes"
                                                                type="number"
                                                                min="1"
                                                                class="text-xs"
                                                                placeholder="60"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <Label class="text-[11px]">Libellé du segment</Label>
                                                        <Input
                                                            v-model="segmentDraft.label"
                                                            class="text-xs"
                                                            placeholder="Ex : Introduction"
                                                        />
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <Label class="text-[11px]">Section ciblée</Label>
                                                        <Select v-model="segmentDraft.section_id">
                                                            <SelectTrigger class="h-7 text-xs">
                                                                <SelectValue placeholder="Choisir une section…" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    v-for="sec in sections"
                                                                    :key="sec.id"
                                                                    :value="sec.id.toString()"
                                                                >
                                                                    {{ sec.label }}
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            class="flex-1 text-xs"
                                                            @click="ajouterSegment(bloc)"
                                                        >
                                                            Ajouter
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            class="text-xs"
                                                            @click="segmentFormBlocId = null"
                                                        >
                                                            Annuler
                                                        </Button>
                                                    </div>
                                                </div>
                                            </template>
                                            <Button
                                                v-else-if="peutEditer"
                                                size="sm"
                                                variant="outline"
                                                class="w-full gap-1 text-xs"
                                                @click="segmentFormBlocId = bloc.id; segmentDraft = { section_id: '', debut_secondes: '', fin_secondes: '', label: '' }"
                                            >
                                                <Plus class="h-3 w-3" />
                                                Ajouter un segment
                                            </Button>
                                        </div>
                                    </template>

                                    <!-- Bouton Sauvegarder -->
                                    <div v-if="peutEditer" class="flex justify-end">
                                        <Button size="sm" @click="sauvegarderBloc(bloc)">
                                            Sauvegarder
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </VueDraggable>

                        <!-- Message liste vide -->
                        <Card v-if="localBlocs.length === 0">
                            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                                Aucun bloc dans cette section. Ajoutez-en un ci-dessous.
                            </CardContent>
                        </Card>

                        <!-- Boutons d'ajout -->
                        <div v-if="peutEditer" class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('texte')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Type class="h-3.5 w-3.5" />
                                Texte
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('image')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <ImageIcon class="h-3.5 w-3.5" />
                                Image
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('carrousel')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <ChevronLeft class="h-3 w-3" />
                                <ChevronRight class="h-3 w-3" />
                                Carrousel
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('video')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Video class="h-3.5 w-3.5" />
                                Vidéo
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                @click="ajouterBloc('separateur')"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <Minus class="h-3.5 w-3.5" />
                                Séparateur
                            </Button>
                        </div>
                    </template>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
