<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

// ─── Types ────────────────────────────────────────────────────────────────────

type Categorie = { id: number; nom: string }

type Musee = {
    slug: string
    titre: string | null
    intro_texte: string | null
    image_path: string | null
    periode: Categorie | null
    thematique: Categorie | null
    region: Categorie | null
    membres: string[]
}

type PaginationLink = { url: string | null; label: string; active: boolean }

type Pagination = {
    data: Musee[]
    links: PaginationLink[]
    current_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
}

type Filtres = {
    periode_id: number | null
    thematique_id: number | null
    region_id: number | null
}

type Props = {
    musees: Pagination
    filtres: Filtres
    options: {
        periodes: Categorie[]
        thematiques: Categorie[]
        regions: Categorie[]
    }
}

const props = defineProps<Props>()

// ─── Filtres ──────────────────────────────────────────────────────────────────

const filtresActifs = ref<Filtres>({ ...props.filtres })

const aucunFiltre = computed(
    () => !filtresActifs.value.periode_id && !filtresActifs.value.thematique_id && !filtresActifs.value.region_id,
)

watch(
    filtresActifs,
    (val) => {
        const params: Record<string, number> = {}
        if (val.periode_id) params.periode_id = val.periode_id
        if (val.thematique_id) params.thematique_id = val.thematique_id
        if (val.region_id) params.region_id = val.region_id
        router.get('/musee', params, { preserveState: true, replace: true })
    },
    { deep: true },
)

function reinitialiserFiltres() {
    filtresActifs.value = { periode_id: null, thematique_id: null, region_id: null }
}

// ─── Utilitaires ──────────────────────────────────────────────────────────────

/**
 * Retourne l'URL de l'image de couverture d'une carte musée.
 * Utilise l'image d'intro si disponible, sinon l'image d'en-tête.
 */
function imageUrl(path: string | null): string | null {
    return path ? `/storage/${path}` : null
}
</script>

<template>
    <Head title="Galerie des musées virtuels" />

    <div class="galerie-musee">
        <!-- ─── En-tête ──────────────────────────────────────────────────── -->
        <header class="galerie-entete">
            <div class="galerie-entete__inner">
                <h1 class="galerie-entete__titre">Galerie des musées virtuels</h1>
                <p class="galerie-entete__sous-titre">
                    Explorez les projets publiés par les étudiants.
                </p>
            </div>
        </header>

        <!-- ─── Barre de filtres ─────────────────────────────────────────── -->
        <div
            v-if="options.periodes.length > 0 || options.thematiques.length > 0 || options.regions.length > 0"
            class="galerie-filtres"
        >
            <div class="galerie-filtres__inner">
                <!-- Période -->
                <div
                    v-if="options.periodes.length > 0"
                    class="galerie-filtres__groupe"
                >
                    <label class="galerie-filtres__label">Période</label>
                    <select
                        v-model="filtresActifs.periode_id"
                        class="galerie-filtres__select"
                    >
                        <option :value="null">Toutes</option>
                        <option
                            v-for="p in options.periodes"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.nom }}
                        </option>
                    </select>
                </div>

                <!-- Thématique -->
                <div
                    v-if="options.thematiques.length > 0"
                    class="galerie-filtres__groupe"
                >
                    <label class="galerie-filtres__label">Thème</label>
                    <select
                        v-model="filtresActifs.thematique_id"
                        class="galerie-filtres__select"
                    >
                        <option :value="null">Tous</option>
                        <option
                            v-for="t in options.thematiques"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.nom }}
                        </option>
                    </select>
                </div>

                <!-- Région -->
                <div
                    v-if="options.regions.length > 0"
                    class="galerie-filtres__groupe"
                >
                    <label class="galerie-filtres__label">Région</label>
                    <select
                        v-model="filtresActifs.region_id"
                        class="galerie-filtres__select"
                    >
                        <option :value="null">Toutes</option>
                        <option
                            v-for="r in options.regions"
                            :key="r.id"
                            :value="r.id"
                        >
                            {{ r.nom }}
                        </option>
                    </select>
                </div>

                <!-- Réinitialiser -->
                <button
                    v-if="!aucunFiltre"
                    type="button"
                    class="galerie-filtres__reinit"
                    @click="reinitialiserFiltres"
                >
                    Réinitialiser
                </button>
            </div>
        </div>

        <!-- ─── Grille de musées ─────────────────────────────────────────── -->
        <main class="galerie-main">
            <div class="galerie-main__inner">
                <!-- Compteur -->
                <p
                    v-if="musees.total > 0"
                    class="galerie-compteur"
                >
                    {{ musees.total }} musée{{ musees.total > 1 ? 's' : '' }}
                </p>

                <!-- Grille de cartes -->
                <div
                    v-if="musees.data.length > 0"
                    class="galerie-grille"
                >
                    <Link
                        v-for="musee in musees.data"
                        :key="musee.slug"
                        :href="`/musee/${musee.slug}`"
                        class="galerie-carte"
                    >
                        <!-- Image de couverture -->
                        <div class="galerie-carte__image-wrap">
                            <img
                                v-if="imageUrl(musee.image_path)"
                                :src="imageUrl(musee.image_path)!"
                                :alt="musee.titre ?? 'Musée virtuel'"
                                class="galerie-carte__image"
                            />
                            <div
                                v-else
                                class="galerie-carte__image-placeholder"
                            />
                        </div>

                        <!-- Contenu de la carte -->
                        <div class="galerie-carte__corps">
                            <!-- Tags catégories -->
                            <div
                                v-if="musee.periode || musee.thematique || musee.region"
                                class="galerie-carte__tags"
                            >
                                <span
                                    v-if="musee.periode"
                                    class="galerie-carte__tag"
                                >{{ musee.periode.nom }}</span>
                                <span
                                    v-if="musee.thematique"
                                    class="galerie-carte__tag"
                                >{{ musee.thematique.nom }}</span>
                                <span
                                    v-if="musee.region"
                                    class="galerie-carte__tag"
                                >{{ musee.region.nom }}</span>
                            </div>

                            <!-- Titre -->
                            <h2 class="galerie-carte__titre">
                                {{ musee.titre ?? 'Musée virtuel' }}
                            </h2>

                            <!-- Intro (tronquée) -->
                            <p
                                v-if="musee.intro_texte"
                                class="galerie-carte__intro"
                            >
                                {{ musee.intro_texte }}
                            </p>

                            <!-- Auteurs -->
                            <p
                                v-if="musee.membres.length > 0"
                                class="galerie-carte__auteurs"
                            >
                                {{ musee.membres.join(' · ') }}
                            </p>
                        </div>
                    </Link>
                </div>

                <!-- Message vide -->
                <div
                    v-else
                    class="galerie-vide"
                >
                    <p v-if="aucunFiltre">Aucun musée virtuel n'est publié pour le moment.</p>
                    <p v-else>Aucun musée ne correspond à ces filtres.</p>
                </div>

                <!-- ─── Pagination ────────────────────────────────────────── -->
                <nav
                    v-if="musees.last_page > 1"
                    class="galerie-pagination"
                    aria-label="Pagination"
                >
                    <template
                        v-for="link in musees.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="['galerie-pagination__lien', link.active ? 'galerie-pagination__lien--actif' : '']"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="galerie-pagination__lien galerie-pagination__lien--inactif"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* ─── Layout global ────────────────────────────────────────────────────────── */

.galerie-musee {
    min-height: 100vh;
    background-color: #f8f7f4;
    color: #1a1a1a;
    font-family: system-ui, -apple-system, sans-serif;
}

/* ─── En-tête ──────────────────────────────────────────────────────────────── */

.galerie-entete {
    background-color: #1a1a2e;
    color: white;
    padding: 3rem 1.5rem;
}

.galerie-entete__inner {
    max-width: 72rem;
    margin: 0 auto;
}

.galerie-entete__titre {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -0.025em;
}

.galerie-entete__sous-titre {
    margin-top: 0.5rem;
    font-size: 1rem;
    opacity: 0.7;
}

/* ─── Filtres ───────────────────────────────────────────────────────────────── */

.galerie-filtres {
    border-bottom: 1px solid #e5e7eb;
    background-color: white;
    padding: 1rem 1.5rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.galerie-filtres__inner {
    max-width: 72rem;
    margin: 0 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 1rem;
}

.galerie-filtres__groupe {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.galerie-filtres__label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.galerie-filtres__select {
    padding: 0.375rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background-color: white;
    color: #1a1a1a;
    cursor: pointer;
}

.galerie-filtres__select:focus {
    outline: 2px solid #1a1a2e;
    outline-offset: 2px;
}

.galerie-filtres__reinit {
    padding: 0.375rem 0.875rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: none;
    cursor: pointer;
    color: #6b7280;
    align-self: flex-end;
    transition: background-color 0.15s;
}

.galerie-filtres__reinit:hover {
    background-color: #f3f4f6;
}

/* ─── Contenu principal ─────────────────────────────────────────────────────── */

.galerie-main {
    padding: 2.5rem 1.5rem;
}

.galerie-main__inner {
    max-width: 72rem;
    margin: 0 auto;
}

.galerie-compteur {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1.5rem;
}

/* ─── Grille ────────────────────────────────────────────────────────────────── */

.galerie-grille {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
    gap: 1.5rem;
}

/* ─── Carte musée ───────────────────────────────────────────────────────────── */

.galerie-carte {
    display: flex;
    flex-direction: column;
    background-color: white;
    border-radius: 0.75rem;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    border: 1px solid #e5e7eb;
    transition: box-shadow 0.2s, transform 0.2s;
}

.galerie-carte:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.galerie-carte__image-wrap {
    height: 12rem;
    overflow: hidden;
    background-color: #e9e6df;
}

.galerie-carte__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s;
}

.galerie-carte:hover .galerie-carte__image {
    transform: scale(1.03);
}

.galerie-carte__image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    opacity: 0.6;
}

.galerie-carte__corps {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1.25rem;
    flex: 1;
}

.galerie-carte__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.galerie-carte__tag {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background-color: #f0efeb;
    color: #4b5563;
}

.galerie-carte__titre {
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
    color: #1a1a1a;
    margin: 0;
}

.galerie-carte__intro {
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.55;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    margin: 0;
}

.galerie-carte__auteurs {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: auto;
    padding-top: 0.5rem;
    border-top: 1px solid #f3f4f6;
}

/* ─── Vide ──────────────────────────────────────────────────────────────────── */

.galerie-vide {
    text-align: center;
    padding: 4rem 0;
    color: #9ca3af;
    font-size: 1rem;
}

/* ─── Pagination ────────────────────────────────────────────────────────────── */

.galerie-pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.25rem;
    margin-top: 3rem;
}

.galerie-pagination__lien {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    height: 2.25rem;
    padding: 0 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    text-decoration: none;
    border: 1px solid #e5e7eb;
    background-color: white;
    color: #374151;
    transition: background-color 0.15s;
}

.galerie-pagination__lien:hover:not(.galerie-pagination__lien--actif):not(.galerie-pagination__lien--inactif) {
    background-color: #f3f4f6;
}

.galerie-pagination__lien--actif {
    background-color: #1a1a2e;
    color: white;
    border-color: #1a1a2e;
    font-weight: 600;
}

.galerie-pagination__lien--inactif {
    color: #9ca3af;
    cursor: default;
    border-color: transparent;
    background: none;
}
</style>
