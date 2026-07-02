<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ChevronDown, List } from 'lucide-vue-next'

// ─── Types ────────────────────────────────────────────────────────────────────

type CategorieMeta = { id: number; nom: string }
type Meta = {
    slug: string
    intro_texte: string | null
    intro_image_path: string | null
    entete_titre: string | null
    entete_sous_titre: string | null
    entete_overlay_couleur: string | null
    entete_image_position: string
    entete_image_path: string | null
    periode: CategorieMeta | null
    thematique: CategorieMeta | null
    region: CategorieMeta | null
}
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
type Bloc = {
    id: number
    type: 'texte' | 'image' | 'separateur' | 'carrousel' | 'video'
    contenu: BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | BlocContenuVideo | null
    ordre: number
    segments: VideoSegment[]
}
type Section = { id: number; label: string; ordre: number; blocs: Bloc[] }
type MuseeImage = { id: number; url: string; alt: string; legende: string; crop_data?: { x: number; y: number } | null }

type Props = {
    meta: Meta
    sections: Section[]
    images: MuseeImage[]
    membres: { id: number; nom: string }[]
    cssVars: Record<string, string>
    theme: string | null
    palette: string | null
    nbVues: number
    typeProjet: { id: number; nom: string }
    sectionsIndex: Record<number, string>
}

const props = defineProps<Props>()

// ─── Résolution des images par ID ─────────────────────────────────────────────

const imageMap = computed(() => {
    const map = new Map<number, MuseeImage>()
    props.images.forEach((img) => map.set(img.id, img))
    return map
})

function imageParId(id: number | null): MuseeImage | undefined {
    return id ? imageMap.value.get(id) : undefined
}

/**
 * Retourne le style CSS `object-position` calculé depuis les données de crop d'une image.
 * Utilisé pour simuler le recadrage sans régénérer l'image.
 */
function cropStyle(img: MuseeImage | undefined): string {
    if (!img?.crop_data) return 'object-fit: cover'
    return `object-fit: cover; object-position: ${img.crop_data.x}% ${img.crop_data.y}%`
}

// ─── CSS variables du template ────────────────────────────────────────────────

const cssVarsStyle = computed(() => props.cssVars)

// Filtre les sections qui ont au moins un bloc visible
const sectionsAvecContenu = computed(() =>
    props.sections.filter((s) => s.blocs.length > 0),
)

const titreOnglet = computed(
    () => props.meta.entete_titre ?? props.typeProjet.nom,
)

// URL de l'image d'en-tête
const enteteImageUrl = computed(() =>
    props.meta.entete_image_path
        ? `/storage/${props.meta.entete_image_path}`
        : null,
)

const enteteBackgroundPosition = computed(() => {
    switch (props.meta.entete_image_position) {
        case 'top':
            return 'center top'
        case 'bottom':
            return 'center bottom'
        default:
            return 'center center'
    }
})

// ─── Carrousel : index par bloc ───────────────────────────────────────────────

const carrouselIndexes = ref<Record<number, number>>({})

// ─── Vidéo : player refs + temps courant ──────────────────────────────────────

const videoElems = ref<Record<number, HTMLVideoElement | null>>({})
const videoCurrentTimes = ref<Record<number, number>>({})

/**
 * Retourne une fonction de ref pour lier dynamiquement un élément vidéo par ID de bloc.
 * Utilisé avec `:ref` dans v-for pour éviter les collisions de noms.
 */
function setVideoElem(blocId: number) {
    return (el: Element | null) => {
        videoElems.value[blocId] = el as HTMLVideoElement | null
    }
}

/** Met à jour le temps courant d'un player, utilisé pour détecter le segment actif. */
function onTimeUpdate(blocId: number, event: Event) {
    videoCurrentTimes.value = {
        ...videoCurrentTimes.value,
        [blocId]: (event.target as HTMLVideoElement).currentTime,
    }
}

/** Positionne la lecture d'une vidéo uploadée au timestamp donné et démarre. */
function seekTo(blocId: number, secondes: number) {
    const video = videoElems.value[blocId]
    if (!video) return
    video.currentTime = secondes
    video.play()
}

/** Retourne l'ID du segment actif selon l'heure courante du player. */
function activeSegmentId(bloc: Bloc): number | null {
    if (bloc.type !== 'video' || !bloc.segments?.length) return null
    const t = videoCurrentTimes.value[bloc.id] ?? 0
    return bloc.segments.find((s) => t >= s.debut_secondes && t <= s.fin_secondes)?.id ?? null
}

// ─── Table des matières ───────────────────────────────────────────────────────

/** Sections ayant au moins un bloc — celles affichées dans la TOC. */
const sectionsToc = computed(() => props.sections.filter((s) => s.blocs.length > 0))

/** Indique si la TOC est visible (2+ sections seulement). */
const hasToc = computed(() => sectionsToc.value.length >= 2)

/** ID de la section active selon le scroll (IntersectionObserver). */
const sectionActiveId = ref<number | null>(null)

/** Contrôle l'ouverture du menu TOC sur mobile. */
const tocMobileOuvert = ref(false)

let tocObserver: IntersectionObserver | null = null

onMounted(() => {
    if (!hasToc.value) return

    // IntersectionObserver : la première section visible du haut devient active
    tocObserver = new IntersectionObserver(
        (entries) => {
            // On itère les entrées en ordre d'apparition et on prend la première visible
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    const id = parseInt(entry.target.id.replace('section-', ''))
                    sectionActiveId.value = id
                }
            }
        },
        { rootMargin: '-15% 0px -75% 0px', threshold: 0 },
    )

    sectionsToc.value.forEach((s) => {
        const el = document.getElementById(`section-${s.id}`)
        if (el) tocObserver!.observe(el)
    })
})

onUnmounted(() => tocObserver?.disconnect())

/** Fait défiler jusqu'à la section dont l'id est passé en paramètre. */
function naviguerVersSection(sectionId: number) {
    document.getElementById(`section-${sectionId}`)?.scrollIntoView({ behavior: 'smooth' })
    tocMobileOuvert.value = false
}

/**
 * Intercepte les clics sur les hyperliens internes (`href="#section-X"`) dans la prose
 * pour déclencher le scroll fluide plutôt que le comportement anchor natif du navigateur.
 */
function interceptLiensInternes(event: MouseEvent) {
    const link = (event.target as HTMLElement).closest('a') as HTMLAnchorElement | null
    if (!link) return

    const href = link.getAttribute('href') ?? ''
    const match = href.match(/^#section-(\d+)$/)
    if (!match) return

    event.preventDefault()
    naviguerVersSection(parseInt(match[1]))
}

/** Gère le clic sur un segment : seek (upload seulement) + navigation vers la section. */
function cliqueSurSegment(bloc: Bloc, seg: VideoSegment) {
    if ((bloc.contenu as BlocContenuVideo).source === 'upload') {
        seekTo(bloc.id, seg.debut_secondes)
    }
    naviguerVersSection(seg.section_id)
}

/** Extrait l'URL d'embed YouTube depuis différents formats d'URL. */
function youtubeEmbedUrl(url: string): string {
    const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([^&?/\s]+)/)
    return m ? `https://www.youtube.com/embed/${m[1]}` : ''
}

/** Extrait l'URL d'embed Vimeo depuis différents formats d'URL. */
function vimeoEmbedUrl(url: string): string {
    const m = url.match(/vimeo\.com\/(\d+)/)
    return m ? `https://player.vimeo.com/video/${m[1]}` : ''
}

/** Formate des secondes entières en mm:ss pour l'affichage dans les segments. */
function formatTemps(secondes: number): string {
    const m = Math.floor(secondes / 60)
    const s = secondes % 60
    return `${m}:${s.toString().padStart(2, '0')}`
}

function getCarrouselIndex(blocId: number): number {
    return carrouselIndexes.value[blocId] ?? 0
}

function setCarrouselIndex(blocId: number, index: number, total: number): void {
    const clamped = Math.max(0, Math.min(total - 1, index))
    carrouselIndexes.value = { ...carrouselIndexes.value, [blocId]: clamped }
}
</script>

<template>
    <div class="musee-public" :style="cssVarsStyle">
        <Head :title="titreOnglet" />

        <!-- ─── En-tête ─────────────────────────────────────────────────────── -->
        <header
            class="relative flex min-h-[30vh] flex-col items-center justify-end overflow-hidden pb-8"
            :style="{
                backgroundImage: enteteImageUrl ? `url(${enteteImageUrl})` : undefined,
                backgroundSize: 'cover',
                backgroundPosition: enteteBackgroundPosition,
                backgroundColor: enteteImageUrl ? undefined : 'var(--musee-couleur-accent, hsl(220 14% 28%))',
            }"
        >
            <!-- Overlay -->
            <div
                v-if="enteteImageUrl"
                class="absolute inset-0"
                :style="{ backgroundColor: meta.entete_overlay_couleur ?? 'rgba(0,0,0,0.4)' }"
            />

            <div class="relative z-10 mx-auto max-w-3xl px-6 text-center">
                <h1
                    v-if="meta.entete_titre"
                    class="text-3xl font-bold leading-tight text-white drop-shadow-md md:text-4xl"
                    style="font-family: var(--musee-font-titre-page, inherit)"
                >
                    {{ meta.entete_titre }}
                </h1>
                <p
                    v-if="meta.entete_sous_titre"
                    class="mt-2 text-lg text-white/90 drop-shadow"
                    style="font-family: var(--musee-font-sous-titre, inherit)"
                >
                    {{ meta.entete_sous_titre }}
                </p>

                <!-- Catégorisation -->
                <div
                    v-if="meta.periode || meta.thematique || meta.region"
                    class="mt-4 flex flex-wrap justify-center gap-2"
                >
                    <span
                        v-if="meta.periode"
                        class="rounded-full bg-white/20 px-3 py-1 text-sm text-white backdrop-blur-sm"
                    >{{ meta.periode.nom }}</span>
                    <span
                        v-if="meta.thematique"
                        class="rounded-full bg-white/20 px-3 py-1 text-sm text-white backdrop-blur-sm"
                    >{{ meta.thematique.nom }}</span>
                    <span
                        v-if="meta.region"
                        class="rounded-full bg-white/20 px-3 py-1 text-sm text-white backdrop-blur-sm"
                    >{{ meta.region.nom }}</span>
                </div>
            </div>
        </header>

        <!-- ─── Table des matières ──────────────────────────────────────────── -->
        <nav
            v-if="hasToc"
            class="musee-toc sticky top-0 z-30 border-b"
            style="background-color: var(--musee-couleur-fond, var(--background, white))"
            aria-label="Table des matières"
        >
            <!-- Desktop : liens horizontaux -->
            <ul class="musee-toc__liste hidden md:flex">
                <li
                    v-for="s in sectionsToc"
                    :key="s.id"
                >
                    <button
                        type="button"
                        :class="[
                            'musee-toc__lien',
                            sectionActiveId === s.id ? 'musee-toc__lien--actif' : '',
                        ]"
                        @click="naviguerVersSection(s.id)"
                    >
                        {{ s.label }}
                    </button>
                </li>
            </ul>

            <!-- Mobile : dropdown -->
            <div class="flex items-center justify-between px-4 py-2 md:hidden">
                <button
                    type="button"
                    class="flex items-center gap-1.5 text-sm font-medium"
                    style="color: var(--musee-couleur-titre, inherit)"
                    @click="tocMobileOuvert = !tocMobileOuvert"
                >
                    <List class="h-4 w-4 shrink-0" />
                    {{
                        sectionActiveId
                            ? (sectionsToc.find((s) => s.id === sectionActiveId)?.label ?? 'Sections')
                            : 'Sections'
                    }}
                    <ChevronDown
                        :class="['h-3.5 w-3.5 transition-transform', tocMobileOuvert ? 'rotate-180' : '']"
                    />
                </button>
            </div>

            <!-- Mobile : liste déroulante -->
            <ul
                v-if="tocMobileOuvert"
                class="border-t md:hidden"
            >
                <li
                    v-for="s in sectionsToc"
                    :key="s.id"
                >
                    <button
                        type="button"
                        :class="[
                            'w-full px-4 py-2 text-left text-sm',
                            sectionActiveId === s.id
                                ? 'font-semibold'
                                : 'opacity-75',
                        ]"
                        :style="sectionActiveId === s.id ? 'color: var(--musee-couleur-accent, inherit)' : ''"
                        @click="naviguerVersSection(s.id)"
                    >
                        {{ s.label }}
                    </button>
                </li>
            </ul>
        </nav>

        <!-- ─── Contenu principal ───────────────────────────────────────────── -->
        <main
            class="mx-auto max-w-3xl px-6 py-10"
            style="
                color: var(--musee-couleur-corps, inherit);
                background-color: var(--musee-couleur-fond, transparent);
                font-family: var(--musee-font-corps, inherit);
            "
            @click.capture="interceptLiensInternes"
        >
            <!-- Texte d'introduction -->
            <div
                v-if="meta.intro_texte"
                class="mb-10 rounded-lg border-l-4 py-4 pl-5 text-base leading-relaxed"
                style="border-color: var(--musee-couleur-accent, currentColor)"
            >
                <p>{{ meta.intro_texte }}</p>
            </div>

            <!-- Sections avec leurs blocs -->
            <section
                v-for="section in sectionsAvecContenu"
                :key="section.id"
                :id="`section-${section.id}`"
                class="mb-12"
            >
                <!-- Titre de section -->
                <h2
                    class="mb-6 text-xl font-semibold"
                    style="
                        color: var(--musee-couleur-titre, inherit);
                        font-family: var(--musee-font-titre-section, inherit);
                    "
                >
                    {{ section.label }}
                </h2>

                <!-- Blocs de la section -->
                <div
                    v-for="bloc in section.blocs"
                    :key="bloc.id"
                    class="mb-6"
                >
                    <!-- Bloc texte (avec image ancrée optionnelle) -->
                    <div
                        v-if="bloc.type === 'texte' && bloc.contenu"
                        :class="[
                            'musee-texte-bloc',
                            (bloc.contenu as BlocContenuTexte).image_ancree?.image_id ? 'musee-avec-image-ancree' : '',
                        ]"
                    >
                        <!-- Image ancrée flottante -->
                        <figure
                            v-if="(bloc.contenu as BlocContenuTexte).image_ancree?.image_id && imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!)"
                            :class="[
                                'musee-image-ancree',
                                (bloc.contenu as BlocContenuTexte).image_ancree!.position === 'gauche'
                                    ? 'musee-image-ancree--gauche'
                                    : 'musee-image-ancree--droite',
                            ]"
                        >
                            <img
                                :src="imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!)!.url"
                                :alt="imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!)!.alt"
                                class="musee-image-ancree__img"
                                :style="cropStyle(imageParId((bloc.contenu as BlocContenuTexte).image_ancree!.image_id!))"
                            />
                        </figure>

                        <div
                            class="musee-prose"
                            style="font-family: var(--musee-font-corps, inherit)"
                            v-html="(bloc.contenu as BlocContenuTexte).html"
                        />
                        <div class="musee-clearfix" />
                    </div>

                    <!-- Bloc image -->
                    <figure
                        v-else-if="bloc.type === 'image' && bloc.contenu && (bloc.contenu as BlocContenuImage).image_id"
                        class="mx-auto max-w-xl"
                    >
                        <img
                            v-if="imageParId((bloc.contenu as BlocContenuImage).image_id!)"
                            :src="imageParId((bloc.contenu as BlocContenuImage).image_id!)!.url"
                            :alt="(bloc.contenu as BlocContenuImage).alt || imageParId((bloc.contenu as BlocContenuImage).image_id!)!.alt"
                            class="w-full rounded-lg"
                            :style="cropStyle(imageParId((bloc.contenu as BlocContenuImage).image_id!))"
                        />
                        <figcaption
                            v-if="(bloc.contenu as BlocContenuImage).legende"
                            class="mt-2 text-center text-sm"
                            style="
                                color: var(--musee-couleur-corps, inherit);
                                font-family: var(--musee-font-legende, inherit);
                                opacity: 0.7;
                            "
                        >
                            {{ (bloc.contenu as BlocContenuImage).legende }}
                        </figcaption>
                    </figure>

                    <!-- Bloc carrousel (tâche 4.2) -->
                    <div
                        v-else-if="bloc.type === 'carrousel' && bloc.contenu && (bloc.contenu as BlocContenuCarrousel).images.length > 0"
                        class="musee-carrousel"
                    >
                        <div class="musee-carrousel__piste-wrap">
                            <div
                                class="musee-carrousel__piste"
                                :style="{
                                    transform: `translateX(-${getCarrouselIndex(bloc.id) * 100}%)`,
                                }"
                            >
                                <figure
                                    v-for="(item, idx) in (bloc.contenu as BlocContenuCarrousel).images"
                                    :key="idx"
                                    class="musee-carrousel__diapo"
                                >
                                    <img
                                        v-if="imageParId(item.image_id)"
                                        :src="imageParId(item.image_id)!.url"
                                        :alt="item.alt || imageParId(item.image_id)!.alt"
                                        class="musee-carrousel__img"
                                        :style="cropStyle(imageParId(item.image_id))"
                                    />
                                    <figcaption
                                        v-if="item.legende"
                                        class="musee-carrousel__legende"
                                        style="font-family: var(--musee-font-legende, inherit)"
                                    >
                                        {{ item.legende }}
                                    </figcaption>
                                </figure>
                            </div>

                            <!-- Flèches -->
                            <button
                                v-if="(bloc.contenu as BlocContenuCarrousel).images.length > 1"
                                type="button"
                                class="musee-carrousel__fleche musee-carrousel__fleche--gauche"
                                aria-label="Image précédente"
                                @click="setCarrouselIndex(bloc.id, getCarrouselIndex(bloc.id) - 1, (bloc.contenu as BlocContenuCarrousel).images.length)"
                            >
                                ‹
                            </button>
                            <button
                                v-if="(bloc.contenu as BlocContenuCarrousel).images.length > 1"
                                type="button"
                                class="musee-carrousel__fleche musee-carrousel__fleche--droite"
                                aria-label="Image suivante"
                                @click="setCarrouselIndex(bloc.id, getCarrouselIndex(bloc.id) + 1, (bloc.contenu as BlocContenuCarrousel).images.length)"
                            >
                                ›
                            </button>
                        </div>

                        <!-- Points de navigation -->
                        <div
                            v-if="(bloc.contenu as BlocContenuCarrousel).images.length > 1"
                            class="musee-carrousel__points"
                        >
                            <button
                                v-for="(_, idx) in (bloc.contenu as BlocContenuCarrousel).images"
                                :key="idx"
                                type="button"
                                :class="[
                                    'musee-carrousel__point',
                                    getCarrouselIndex(bloc.id) === idx ? 'musee-carrousel__point--actif' : '',
                                ]"
                                :aria-label="`Image ${idx + 1}`"
                                @click="setCarrouselIndex(bloc.id, idx, (bloc.contenu as BlocContenuCarrousel).images.length)"
                            />
                        </div>
                    </div>

                    <!-- Bloc vidéo -->
                    <figure
                        v-else-if="bloc.type === 'video' && bloc.contenu"
                        class="musee-video-bloc"
                    >
                        <!-- Vidéo uploadée (HTML5 player) -->
                        <div
                            v-if="(bloc.contenu as BlocContenuVideo).source === 'upload' && (bloc.contenu as BlocContenuVideo).groupe_video_id"
                            class="musee-video__wrap"
                        >
                            <video
                                :ref="setVideoElem(bloc.id)"
                                controls
                                class="musee-video__player"
                                :src="`/musee/${meta.slug}/video/${bloc.id}`"
                                preload="metadata"
                                @timeupdate="onTimeUpdate(bloc.id, $event)"
                            />
                        </div>

                        <!-- Embed YouTube -->
                        <div
                            v-else-if="(bloc.contenu as BlocContenuVideo).source === 'youtube' && youtubeEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                            class="musee-video__embed-wrap"
                        >
                            <iframe
                                :src="youtubeEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                class="musee-video__embed"
                                frameborder="0"
                                allowfullscreen
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                title="Vidéo YouTube"
                            />
                        </div>

                        <!-- Embed Vimeo -->
                        <div
                            v-else-if="(bloc.contenu as BlocContenuVideo).source === 'vimeo' && vimeoEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                            class="musee-video__embed-wrap"
                        >
                            <iframe
                                :src="vimeoEmbedUrl((bloc.contenu as BlocContenuVideo).url_externe ?? '')"
                                class="musee-video__embed"
                                frameborder="0"
                                allowfullscreen
                                title="Vidéo Vimeo"
                            />
                        </div>

                        <!-- Légende -->
                        <figcaption
                            v-if="(bloc.contenu as BlocContenuVideo).legende"
                            class="mt-2 text-center text-sm"
                            style="color: var(--musee-couleur-corps, inherit); font-family: var(--musee-font-legende, inherit); opacity: 0.7"
                        >
                            {{ (bloc.contenu as BlocContenuVideo).legende }}
                        </figcaption>

                        <!-- Segments / chapitres -->
                        <div
                            v-if="bloc.segments && bloc.segments.length > 0"
                            class="musee-video__segments"
                        >
                            <p class="musee-video__segments-titre">Chapitres</p>
                            <ul class="musee-video__segments-liste">
                                <li
                                    v-for="seg in bloc.segments"
                                    :key="seg.id"
                                    :class="[
                                        'musee-video__segment',
                                        activeSegmentId(bloc) === seg.id ? 'musee-video__segment--actif' : '',
                                    ]"
                                >
                                    <button
                                        type="button"
                                        class="musee-video__segment-btn"
                                        @click="cliqueSurSegment(bloc, seg)"
                                    >
                                        <span class="musee-video__segment-temps">
                                            {{ formatTemps(seg.debut_secondes) }}
                                        </span>
                                        <span class="musee-video__segment-label">{{ seg.label }}</span>
                                        <span
                                            v-if="props.sectionsIndex[seg.section_id]"
                                            class="musee-video__segment-section"
                                        >
                                            → {{ props.sectionsIndex[seg.section_id] }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </figure>

                    <!-- Bloc séparateur -->
                    <hr
                        v-else-if="bloc.type === 'separateur'"
                        class="my-8 border-t"
                        style="border-color: var(--musee-couleur-accent, currentColor); opacity: 0.3"
                    />
                </div>
            </section>

            <!-- Message si aucun contenu -->
            <div
                v-if="sectionsAvecContenu.length === 0"
                class="py-12 text-center text-base text-gray-400"
            >
                Ce musée virtuel est en cours de construction.
            </div>
        </main>

        <!-- ─── Pied de page ────────────────────────────────────────────────── -->
        <footer
            class="border-t px-6 py-8 text-center text-sm"
            style="
                color: var(--musee-couleur-corps, inherit);
                opacity: 0.7;
            "
        >
            <p class="font-medium">{{ typeProjet.nom }}</p>
            <p v-if="membres.length > 0" class="mt-1">
                {{ membres.map((m) => m.nom).join(' · ') }}
            </p>
            <p class="mt-2 text-xs opacity-70">{{ nbVues }} {{ nbVues === 1 ? 'visite' : 'visites' }}</p>
        </footer>
    </div>
</template>

<style scoped>
/* ─── Table des matières ───────────────────────────────────────────────────── */

.musee-toc__liste {
    gap: 0;
    overflow-x: auto;
    white-space: nowrap;
    padding: 0 1rem;
}

.musee-toc__lien {
    display: inline-block;
    padding: 0.5rem 0.875rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--musee-couleur-corps, inherit);
    opacity: 0.65;
    border-bottom: 2px solid transparent;
    transition: opacity 0.15s, border-color 0.15s;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
    cursor: pointer;
    white-space: nowrap;
}

.musee-toc__lien:hover {
    opacity: 1;
}

.musee-toc__lien--actif {
    opacity: 1;
    font-weight: 600;
    color: var(--musee-couleur-accent, inherit);
    border-bottom-color: var(--musee-couleur-accent, currentColor);
}

/* ─── Styles de la prose publique ─────────────────────────────────────────── */

:deep(.musee-prose h2) {
    font-family: var(--musee-font-titre-section, inherit);
    color: var(--musee-couleur-titre, inherit);
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

:deep(.musee-prose h3) {
    font-family: var(--musee-font-titre-section, inherit);
    color: var(--musee-couleur-titre, inherit);
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.375rem;
}

:deep(.musee-prose p) {
    margin-bottom: 0.875rem;
    line-height: 1.75;
}

:deep(.musee-prose ul) {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-bottom: 0.875rem;
}

:deep(.musee-prose ol) {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-bottom: 0.875rem;
}

:deep(.musee-prose blockquote) {
    border-left: 4px solid var(--musee-couleur-accent, #ccc);
    padding-left: 1rem;
    font-style: italic;
    opacity: 0.85;
    margin: 1rem 0;
}

:deep(.musee-prose a[data-externe='true']) {
    color: var(--musee-couleur-lien-externe, #2563eb);
    text-decoration: underline;
}

:deep(.musee-prose a[data-externe='true'])::after {
    content: ' ↗';
    font-size: 0.75em;
}

:deep(.musee-prose strong) {
    font-weight: 700;
}

:deep(.musee-prose em) {
    font-style: italic;
}

/* ─── Image ancrée / flottante ─────────────────────────────────────────────── */

.musee-image-ancree {
    margin-bottom: 0.5rem;
    max-width: 40%;
}

.musee-image-ancree--gauche {
    float: left;
    margin-right: 1.25rem;
}

.musee-image-ancree--droite {
    float: right;
    margin-left: 1.25rem;
}

.musee-image-ancree__img {
    width: 100%;
    height: auto;
    border-radius: 0.375rem;
    object-fit: cover;
}

.musee-clearfix::after {
    content: '';
    display: table;
    clear: both;
}

/* ─── Carrousel ───────────────────────────────────────────────────────────── */

.musee-carrousel {
    width: 100%;
}

.musee-carrousel__piste-wrap {
    position: relative;
    overflow: hidden;
    border-radius: 0.5rem;
}

.musee-carrousel__piste {
    display: flex;
    transition: transform 0.4s ease;
}

.musee-carrousel__diapo {
    min-width: 100%;
    margin: 0;
}

.musee-carrousel__img {
    width: 100%;
    height: 24rem;
    object-fit: cover;
    display: block;
}

.musee-carrousel__legende {
    text-align: center;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    opacity: 0.75;
    color: var(--musee-couleur-corps, inherit);
}

.musee-carrousel__fleche {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.45);
    color: white;
    border: none;
    font-size: 2rem;
    line-height: 1;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.musee-carrousel__fleche:hover {
    background-color: rgba(0, 0, 0, 0.65);
}

.musee-carrousel__fleche--gauche {
    left: 0.5rem;
}

.musee-carrousel__fleche--droite {
    right: 0.5rem;
}

.musee-carrousel__points {
    display: flex;
    justify-content: center;
    gap: 0.375rem;
    margin-top: 0.625rem;
}

.musee-carrousel__point {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    border: none;
    background-color: var(--musee-couleur-corps, #999);
    opacity: 0.35;
    cursor: pointer;
    padding: 0;
    transition: opacity 0.2s;
}

.musee-carrousel__point--actif {
    opacity: 1;
    background-color: var(--musee-couleur-accent, currentColor);
}

/* ─── Bloc vidéo ───────────────────────────────────────────────────────────── */

.musee-video-bloc {
    margin: 0;
    width: 100%;
}

.musee-video__wrap {
    border-radius: 0.5rem;
    overflow: hidden;
    background: #000;
}

.musee-video__player {
    width: 100%;
    display: block;
    max-height: 32rem;
}

/* Ratio 16:9 pour les embeds YouTube / Vimeo */
.musee-video__embed-wrap {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    border-radius: 0.5rem;
    overflow: hidden;
}

.musee-video__embed {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
}

/* Segments */
.musee-video__segments {
    margin-top: 0.75rem;
    border: 1px solid var(--musee-couleur-accent, #ccc);
    border-radius: 0.5rem;
    overflow: hidden;
}

.musee-video__segments-titre {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background-color: var(--musee-couleur-accent, #555);
    color: #fff;
    opacity: 0.9;
}

.musee-video__segments-liste {
    list-style: none;
    padding: 0;
    margin: 0;
}

.musee-video__segment {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    transition: background-color 0.15s;
}

.musee-video__segment:last-child {
    border-bottom: none;
}

.musee-video__segment--actif {
    background-color: color-mix(in srgb, var(--musee-couleur-accent, #4f6ef7) 12%, transparent);
}

.musee-video__segment-btn {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    font-size: 0.875rem;
    color: var(--musee-couleur-corps, inherit);
    transition: background-color 0.15s;
}

.musee-video__segment-btn:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.musee-video__segment-temps {
    font-family: monospace;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--musee-couleur-accent, #4f6ef7);
    white-space: nowrap;
    min-width: 2.5rem;
}

.musee-video__segment-label {
    flex: 1;
    font-weight: 500;
}

.musee-video__segment-section {
    font-size: 0.75rem;
    opacity: 0.6;
    white-space: nowrap;
}
</style>
