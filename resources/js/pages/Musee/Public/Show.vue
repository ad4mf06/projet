<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

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
type Bloc = {
    id: number
    type: 'texte' | 'image' | 'separateur' | 'carrousel'
    contenu: BlocContenuTexte | BlocContenuImage | BlocContenuCarrousel | null
    ordre: number
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

        <!-- ─── Contenu principal ───────────────────────────────────────────── -->
        <main
            class="mx-auto max-w-3xl px-6 py-10"
            style="
                color: var(--musee-couleur-corps, inherit);
                background-color: var(--musee-couleur-fond, transparent);
                font-family: var(--musee-font-corps, inherit);
            "
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
</style>
