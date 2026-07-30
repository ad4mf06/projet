<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import sections from '@/routes/types-projets/musee-template/sections';

// ── Types ──────────────────────────────────────────────────────────────────────

type ZoneType = 'texte' | 'image' | 'carrousel' | 'video' | 'audio' | 'vide';

type Zone = {
    id: string;
    type: ZoneType;
    label: string;
    x: number;
    y: number;
    w: number;
    h: number;
    ordre_mobile: number;
};

export type Canevas = {
    hauteur_vw: number;
    /** Écart en px entre zones (appliqué comme inset sur le fond visuel). */
    gap: number;
    zones: Zone[];
};

type Props = {
    sectionId: number;
    sectionLabel: string;
    coursId: number;
    typeProjetId: number;
    initialCanevas: Canevas | null;
};

type HandleType = 'nw' | 'ne' | 'sw' | 'se';

type DragState = {
    mode: 'deplacer' | 'redimensionner';
    handle?: HandleType;
    zoneId: string;
    startMouseX: number;
    startMouseY: number;
    startX: number;
    startY: number;
    startW: number;
    startH: number;
};

// ── Constantes ────────────────────────────────────────────────────────────────

const ZONE_COULEURS: Record<ZoneType, string> = {
    texte: '#3B82F6',
    image: '#10B981',
    video: '#EF4444',
    carrousel: '#F59E0B',
    audio: '#8B5CF6',
    vide: 'transparent',
};

const ZONE_LABELS: Record<ZoneType, string> = {
    texte: 'Texte',
    image: 'Image',
    video: 'Vidéo',
    carrousel: 'Carrousel',
    audio: 'Audio',
    vide: 'Vide (espace)',
};

const TYPES_ZONES: ZoneType[] = ['texte', 'image', 'video', 'carrousel', 'audio', 'vide'];

const GAP_PAR_DEFAUT = 4;

// ── État ──────────────────────────────────────────────────────────────────────

const props = defineProps<Props>();

/** Référence sur le conteneur absolu du canevas, utilisé pour convertir px → %. */
const containerRef = ref<HTMLElement | null>(null);

/** Mode avancé : affiche les champs x/y/w/h de la zone sélectionnée. */
const modeAvance = ref(false);

/** Zone actuellement sélectionnée dans l'aperçu. */
const zoneSelectionneeId = ref<string | null>(null);

/** État du glisser-déplacer ou du redimensionnement en cours. */
const dragState = ref<DragState | null>(null);

function clonerCanevas(c: Canevas): Canevas {
    return {
        hauteur_vw: c.hauteur_vw,
        gap: c.gap ?? GAP_PAR_DEFAUT,
        zones: c.zones.map((z) => ({ ...z })),
    };
}

const canevas = ref<Canevas>(
    props.initialCanevas
        ? clonerCanevas(props.initialCanevas)
        : { hauteur_vw: 56, gap: GAP_PAR_DEFAUT, zones: [] },
);

const enCours = ref(false);

// ── Méthodes ──────────────────────────────────────────────────────────────────

/**
 * Arrondit à 1 décimale pour éviter les positions flottantes trop précises.
 */
function arrondir(v: number): number {
    return Math.round(v * 10) / 10;
}

/**
 * Ajoute une zone au centre du canevas.
 */
function ajouterZone(): void {
    const ordre = canevas.value.zones.length + 1;
    const id = `z${Date.now()}`;
    canevas.value.zones.push({
        id,
        type: 'texte',
        label: `Zone ${ordre}`,
        x: 10,
        y: 10,
        w: 40,
        h: 40,
        ordre_mobile: ordre,
    });
    zoneSelectionneeId.value = id;
}

/**
 * Supprime la zone à l'index donné et réindexe ordre_mobile.
 */
function supprimerZone(idx: number): void {
    const idSupprimee = canevas.value.zones[idx].id;
    canevas.value.zones.splice(idx, 1);
    canevas.value.zones.forEach((z, i) => { z.ordre_mobile = i + 1; });
    if (zoneSelectionneeId.value === idSupprimee) {
        zoneSelectionneeId.value = null;
    }
}

/**
 * Réinitialise le canevas (les étudiants retrouveront le mode blocs libre).
 */
function viderCanevas(): void {
    zoneSelectionneeId.value = null;
    canevas.value = { hauteur_vw: 56, gap: GAP_PAR_DEFAUT, zones: [] };
}

/**
 * Sauvegarde le canevas courant via PATCH.
 * Un canevas sans zones est envoyé comme null (mode libre).
 */
function sauvegarder(): void {
    enCours.value = true;
    router.patch(
        sections.canevas.url({ cours: props.coursId, typeProjet: props.typeProjetId, section: props.sectionId }),
        { canevas: canevas.value.zones.length > 0 ? canevas.value : null },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => { enCours.value = false; },
        },
    );
}

// ── Drag & resize ─────────────────────────────────────────────────────────────

/**
 * Démarre le déplacement d'une zone par glisser.
 */
function commencerDeplacement(zone: Zone, e: MouseEvent): void {
    e.preventDefault();
    zoneSelectionneeId.value = zone.id;
    dragState.value = {
        mode: 'deplacer',
        zoneId: zone.id,
        startMouseX: e.clientX,
        startMouseY: e.clientY,
        startX: zone.x,
        startY: zone.y,
        startW: zone.w,
        startH: zone.h,
    };
}

/**
 * Démarre le redimensionnement via l'une des 4 poignées de coin (nw/ne/sw/se).
 * L'angle opposé reste fixe pendant le glissement.
 */
function commencerRedimensionnement(zone: Zone, handle: HandleType, e: MouseEvent): void {
    e.preventDefault();
    e.stopPropagation();
    dragState.value = {
        mode: 'redimensionner',
        handle,
        zoneId: zone.id,
        startMouseX: e.clientX,
        startMouseY: e.clientY,
        startX: zone.x,
        startY: zone.y,
        startW: zone.w,
        startH: zone.h,
    };
}

/**
 * Met à jour la position ou la taille de la zone pendant le glissement.
 * Toutes les valeurs sont exprimées en pourcentage du conteneur.
 */
function onMouseMove(e: MouseEvent): void {
    const state = dragState.value;
    if (!state || !containerRef.value) return;

    const zone = canevas.value.zones.find((z) => z.id === state.zoneId);
    if (!zone) return;

    const cW = containerRef.value.offsetWidth;
    const cH = containerRef.value.offsetHeight;
    const dx = ((e.clientX - state.startMouseX) / cW) * 100;
    const dy = ((e.clientY - state.startMouseY) / cH) * 100;
    const MIN = 5;

    if (state.mode === 'deplacer') {
        zone.x = arrondir(Math.max(0, Math.min(100 - state.startW, state.startX + dx)));
        zone.y = arrondir(Math.max(0, Math.min(100 - state.startH, state.startY + dy)));
        return;
    }

    // Redimensionnement — l'angle opposé au handle reste fixe.
    switch (state.handle) {
        case 'se':
            zone.w = arrondir(Math.max(MIN, Math.min(100 - state.startX, state.startW + dx)));
            zone.h = arrondir(Math.max(MIN, Math.min(100 - state.startY, state.startH + dy)));
            break;

        case 'sw': {
            const newX = Math.max(0, Math.min(state.startX + state.startW - MIN, state.startX + dx));
            zone.x = arrondir(newX);
            zone.w = arrondir(state.startX + state.startW - zone.x);
            zone.h = arrondir(Math.max(MIN, Math.min(100 - state.startY, state.startH + dy)));
            break;
        }

        case 'ne': {
            zone.w = arrondir(Math.max(MIN, Math.min(100 - state.startX, state.startW + dx)));
            const newY = Math.max(0, Math.min(state.startY + state.startH - MIN, state.startY + dy));
            zone.y = arrondir(newY);
            zone.h = arrondir(state.startY + state.startH - zone.y);
            break;
        }

        case 'nw': {
            const newX = Math.max(0, Math.min(state.startX + state.startW - MIN, state.startX + dx));
            zone.x = arrondir(newX);
            zone.w = arrondir(state.startX + state.startW - zone.x);
            const newY = Math.max(0, Math.min(state.startY + state.startH - MIN, state.startY + dy));
            zone.y = arrondir(newY);
            zone.h = arrondir(state.startY + state.startH - zone.y);
            break;
        }
    }
}

/**
 * Termine le glissement.
 */
function onMouseUp(): void {
    dragState.value = null;
}

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
});
</script>

<template>
    <div class="rounded-lg border border-border p-4">
        <!-- Titre de section -->
        <p class="mb-4 text-sm font-semibold">{{ sectionLabel }}</p>

        <!-- Aperçu interactif du canevas -->
        <div v-if="canevas.zones.length > 0" class="mb-4">
            <div class="mb-1.5 flex items-center justify-between gap-4">
                <p class="shrink-0 text-xs font-medium text-muted-foreground">
                    Cliquer · Glisser · Coins pour redimensionner
                </p>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs text-muted-foreground">Hauteur</span>
                        <input
                            v-model.number="canevas.hauteur_vw"
                            type="range"
                            min="20"
                            max="120"
                            step="5"
                            class="w-16"
                        />
                        <span class="w-10 text-xs tabular-nums text-muted-foreground">{{ canevas.hauteur_vw }}vw</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs text-muted-foreground">Écart</span>
                        <input
                            v-model.number="canevas.gap"
                            type="range"
                            min="0"
                            max="20"
                            step="2"
                            class="w-16"
                        />
                        <span class="w-8 text-xs tabular-nums text-muted-foreground">{{ canevas.gap }}px</span>
                    </div>
                </div>
            </div>

            <!-- Zone de canevas interactive -->
            <div
                class="relative w-full overflow-hidden rounded border border-border bg-muted/20"
                :style="{ paddingTop: `${canevas.hauteur_vw}%` }"
                @mousedown.self="zoneSelectionneeId = null"
            >
                <div ref="containerRef" class="absolute inset-0 select-none">
                    <div
                        v-for="zone in canevas.zones"
                        :key="zone.id"
                        class="absolute"
                        :class="zone.id === zoneSelectionneeId ? 'z-10' : ''"
                        :style="{
                            left: `${zone.x}%`,
                            top: `${zone.y}%`,
                            width: `${zone.w}%`,
                            height: `${zone.h}%`,
                            cursor: dragState?.zoneId === zone.id && dragState.mode === 'deplacer'
                                ? 'grabbing'
                                : 'grab',
                        }"
                        @mousedown.stop="commencerDeplacement(zone, $event)"
                    >
                        <!-- Fond visuel inset par le gap global — pointer-events-none pour laisser les
                             événements remonter au wrapper logique -->
                        <div
                            class="pointer-events-none absolute flex items-center justify-center"
                            :class="[
                                zone.type === 'vide'
                                    ? 'border border-dashed border-white/30'
                                    : 'border border-white/50',
                                zone.id === zoneSelectionneeId ? 'ring-2 ring-white' : '',
                            ]"
                            :style="{
                                inset: `${canevas.gap}px`,
                                background: zone.type === 'vide'
                                    ? 'rgba(255,255,255,0.05)'
                                    : ZONE_COULEURS[zone.type] + '70',
                            }"
                        >
                            <span class="truncate px-1 text-center text-[9px] font-semibold leading-tight text-white drop-shadow">
                                {{ zone.label }}
                            </span>
                        </div>

                        <!-- Poignées de redimensionnement aux 4 coins -->
                        <template v-if="zone.id === zoneSelectionneeId">
                            <div
                                class="absolute left-0 top-0 h-3 w-3 -translate-x-1/2 -translate-y-1/2 cursor-nwse-resize rounded-full bg-white shadow"
                                @mousedown.stop="commencerRedimensionnement(zone, 'nw', $event)"
                            />
                            <div
                                class="absolute right-0 top-0 h-3 w-3 -translate-y-1/2 translate-x-1/2 cursor-nesw-resize rounded-full bg-white shadow"
                                @mousedown.stop="commencerRedimensionnement(zone, 'ne', $event)"
                            />
                            <div
                                class="absolute bottom-0 left-0 h-3 w-3 -translate-x-1/2 translate-y-1/2 cursor-nesw-resize rounded-full bg-white shadow"
                                @mousedown.stop="commencerRedimensionnement(zone, 'sw', $event)"
                            />
                            <div
                                class="absolute bottom-0 right-0 h-3 w-3 translate-x-1/2 translate-y-1/2 cursor-nwse-resize rounded-full bg-white shadow"
                                @mousedown.stop="commencerRedimensionnement(zone, 'se', $event)"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des zones éditables -->
        <div v-if="canevas.zones.length > 0" class="mb-3 flex flex-col gap-1.5">
            <div
                v-for="(zone, idx) in canevas.zones"
                :key="zone.id"
                class="rounded border transition-colors"
                :class="zone.id === zoneSelectionneeId
                    ? 'border-primary/40 bg-primary/5'
                    : 'border-border bg-muted/20'"
            >
                <!-- Ligne principale : pastille + label + type + supprimer -->
                <div
                    class="flex cursor-pointer items-center gap-2 p-2"
                    @click="zoneSelectionneeId = zone.id === zoneSelectionneeId ? null : zone.id"
                >
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full border"
                        :class="zone.type === 'vide' ? 'border-dashed border-muted-foreground' : 'border-transparent'"
                        :style="zone.type !== 'vide' ? { background: ZONE_COULEURS[zone.type] } : {}"
                    />
                    <input
                        v-model="zone.label"
                        type="text"
                        placeholder="Nom de la zone…"
                        class="min-w-0 flex-1 rounded border border-border bg-background px-2 py-0.5 text-xs"
                        maxlength="100"
                        @click.stop
                    />
                    <select
                        v-model="zone.type"
                        class="shrink-0 rounded border border-border bg-background px-1.5 py-0.5 text-xs"
                        @click.stop
                    >
                        <option v-for="t in TYPES_ZONES" :key="t" :value="t">
                            {{ ZONE_LABELS[t] }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="shrink-0 text-muted-foreground hover:text-destructive"
                        aria-label="Supprimer cette zone"
                        @click.stop="supprimerZone(idx)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- Contrôles de position fine (mode avancé, zone sélectionnée seulement) -->
                <div
                    v-if="modeAvance && zone.id === zoneSelectionneeId"
                    class="flex items-center gap-2 border-t border-border/50 px-3 py-1.5"
                >
                    <span class="text-[10px] text-muted-foreground">x</span>
                    <input v-model.number="zone.x" type="number" min="0" max="95" class="w-12 rounded border border-border bg-background px-1 py-0.5 text-xs" />
                    <span class="text-[10px] text-muted-foreground">y</span>
                    <input v-model.number="zone.y" type="number" min="0" max="95" class="w-12 rounded border border-border bg-background px-1 py-0.5 text-xs" />
                    <span class="text-[10px] text-muted-foreground">larg.</span>
                    <input v-model.number="zone.w" type="number" min="5" max="100" class="w-12 rounded border border-border bg-background px-1 py-0.5 text-xs" />
                    <span class="text-[10px] text-muted-foreground">haut.</span>
                    <input v-model.number="zone.h" type="number" min="5" max="100" class="w-12 rounded border border-border bg-background px-1 py-0.5 text-xs" />
                    <span class="text-[10px] text-muted-foreground">%</span>
                </div>
            </div>
        </div>

        <!-- Aucune zone : message d'aide -->
        <p v-else class="mb-3 text-xs italic text-muted-foreground">
            Aucun canevas défini — les étudiants ajoutent des blocs librement.
        </p>

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex items-center gap-1 text-xs text-primary hover:underline"
                    @click="ajouterZone"
                >
                    <Plus class="h-3 w-3" />
                    Ajouter une zone
                </button>

                <button
                    v-if="canevas.zones.length > 0"
                    type="button"
                    class="text-xs text-muted-foreground hover:underline"
                    @click="modeAvance = !modeAvance"
                >
                    {{ modeAvance ? 'Masquer positions' : 'Ajuster positions' }}
                </button>

                <button
                    v-if="canevas.zones.length > 0"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-destructive"
                    @click="viderCanevas"
                >
                    Vider
                </button>
            </div>

            <Button
                size="sm"
                variant="outline"
                :disabled="enCours"
                @click="sauvegarder"
            >
                {{ enCours ? 'Enregistrement…' : 'Sauvegarder le canevas' }}
            </Button>
        </div>
    </div>
</template>
