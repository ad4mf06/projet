<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronLeft, ChevronRight, Download, FileText, ImagePlus, Music, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type User = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
    periode_historique: string | null;
};

type Note = {
    id: number;
    contenu: string;
    created_at: string;
    auteur: User;
    user_id: number;
};

type Media = {
    id: number;
    nom_original: string;
    type: 'photo' | 'document' | 'audio';
    taille: number;
    url: string;
    user_id: number;
    auteur: User;
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
    created_by: number;
    classe: Classe;
    membres: User[];
    thematiques: Thematique[];
    notes: Note[];
    medias: Media[];
};

type EtudiantDispo = {
    id: number;
    prenom: string;
    nom: string;
};

type Props = {
    groupe: Groupe;
    estMembre: boolean;
    estEnseignant: boolean;
    estCreateur: boolean;
    thematiquesDispo: Thematique[];
    etudiantsDispo: EtudiantDispo[];
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as any).user?.id);

// ─── Gérer les membres (créateur seulement) ───────────────────────────────────
const showMembresDialog = ref(false);
const membresForm = useForm({
    ajouter: [] as number[],
    retirer: [] as number[],
});

function openMembres() {
    membresForm.ajouter = [];
    membresForm.retirer = [];
    showMembresDialog.value = true;
}

function toggleAjouter(id: number, val: boolean | string) {
    if (val) {
        if (!membresForm.ajouter.includes(id)) membresForm.ajouter.push(id);
    } else {
        membresForm.ajouter = membresForm.ajouter.filter((m) => m !== id);
    }
}

function toggleRetirer(id: number, val: boolean | string) {
    if (val) {
        if (!membresForm.retirer.includes(id)) membresForm.retirer.push(id);
    } else {
        membresForm.retirer = membresForm.retirer.filter((m) => m !== id);
    }
}

function submitMembres() {
    membresForm.put(
        `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/membres`,
        { onSuccess: () => { showMembresDialog.value = false; } },
    );
}

// ─── Modifier les thématiques ─────────────────────────────────────────────────
const showThematiquesDialog = ref(false);
const thematiquesForm = useForm({
    thematiques: [] as number[],
});

function openThematiques() {
    thematiquesForm.thematiques = props.groupe.thematiques.map((t) => t.id);
    showThematiquesDialog.value = true;
}

const thematiquesMax = computed(() => thematiquesForm.thematiques.length >= 3);

function toggleThematique(id: number, val: boolean | string) {
    if (val) {
        if (thematiquesForm.thematiques.length < 3 && !thematiquesForm.thematiques.includes(id)) {
            thematiquesForm.thematiques.push(id);
        }
    } else {
        thematiquesForm.thematiques = thematiquesForm.thematiques.filter((t) => t !== id);
    }
}

function submitThematiques() {
    thematiquesForm.put(
        `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/thematiques`,
        {
            onSuccess: () => {
                showThematiquesDialog.value = false;
            },
        },
    );
}

// ─── Carrousel photos ─────────────────────────────────────────────────────────
const photos = computed(() => props.groupe.medias.filter((m) => m.type === 'photo'));
const documents = computed(() => props.groupe.medias.filter((m) => m.type === 'document'));
const audios = computed(() => props.groupe.medias.filter((m) => m.type === 'audio'));

const photoIndex = ref(0);

function prevPhoto() {
    photoIndex.value = (photoIndex.value - 1 + photos.value.length) % photos.value.length;
}

function nextPhoto() {
    photoIndex.value = (photoIndex.value + 1) % photos.value.length;
}

// ─── Upload média ─────────────────────────────────────────────────────────────
const mediaFileInput = ref<HTMLInputElement | null>(null);
const mediaForm = useForm({ fichier: null as File | null });

function handleMediaChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        mediaForm.fichier = input.files[0];
        mediaForm.post(`/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/medias`, {
            onSuccess: () => {
                mediaForm.reset();
                if (mediaFileInput.value) mediaFileInput.value.value = '';
            },
        });
    }
}

// ─── Supprimer un média ───────────────────────────────────────────────────────
const deleteMediaForm = useForm({});

function deleteMedia(media: Media) {
    if (!confirm(`Supprimer "${media.nom_original}" ?`)) return;
    deleteMediaForm.delete(
        `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/medias/${media.id}`,
    );
}

function peutSupprimerMedia(media: Media): boolean {
    return media.user_id === userId.value || props.estEnseignant;
}

// ─── Nouvelle note ────────────────────────────────────────────────────────────
const noteForm = useForm({ contenu: '' });

function submitNote() {
    noteForm.post(`/groupes/${props.groupe.id}/notes`, {
        onSuccess: () => noteForm.reset(),
    });
}

// ─── Supprimer une note ───────────────────────────────────────────────────────
const deleteNoteForm = useForm({});

function deleteNote(note: Note) {
    if (!confirm('Supprimer cette note ?')) return;
    deleteNoteForm.delete(`/groupes/${props.groupe.id}/notes/${note.id}`);
}

// ─── Formatage ────────────────────────────────────────────────────────────────
function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-CA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
    <AppLayout>
        <Head :title="groupe.nom" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/classes/${groupe.classe_id}/groupes`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour aux groupes
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="groupe.nom"
                :description="`${groupe.classe.code} — Groupe ${groupe.classe.groupe} · ${groupe.classe.nom_cours}`"
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Membres -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Membres</CardTitle>
                        <Button
                            v-if="estCreateur"
                            size="sm"
                            variant="outline"
                            @click="openMembres"
                        >
                            <Pencil class="mr-2 h-4 w-4" />
                            Gérer
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="membre in groupe.membres"
                                :key="membre.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <span
                                    class="bg-primary/10 text-primary flex h-7 w-7 items-center justify-center rounded-full text-xs font-medium"
                                >
                                    {{ membre.prenom[0] }}{{ membre.nom[0] }}
                                </span>
                                <span>{{ membre.prenom }} {{ membre.nom }}</span>
                                <span
                                    v-if="membre.id === groupe.created_by"
                                    class="text-muted-foreground text-xs"
                                >
                                    (créateur)
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Thématiques -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Thématiques</CardTitle>
                        <Button
                            v-if="estMembre"
                            size="sm"
                            variant="outline"
                            @click="openThematiques"
                        >
                            <Pencil class="mr-2 h-4 w-4" />
                            Modifier
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div v-if="groupe.thematiques.length === 0" class="text-muted-foreground text-sm">
                            Aucune thématique sélectionnée.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="thematique in groupe.thematiques"
                                :key="thematique.id"
                            >
                                <p class="font-medium text-sm">{{ thematique.nom }}</p>
                                <p
                                    v-if="thematique.periode_historique"
                                    class="text-muted-foreground text-xs"
                                >
                                    {{ thematique.periode_historique }}
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Carrousel photos -->
            <Card v-if="photos.length > 0">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Photos</CardTitle>
                    <span class="text-muted-foreground text-sm">
                        {{ photoIndex + 1 }} / {{ photos.length }}
                    </span>
                </CardHeader>
                <CardContent>
                    <div class="relative overflow-hidden rounded-lg">
                        <!-- Image -->
                        <img
                            :src="photos[photoIndex].url"
                            :alt="photos[photoIndex].nom_original"
                            class="w-full max-h-96 object-contain bg-muted"
                        />

                        <!-- Boutons navigation -->
                        <button
                            v-if="photos.length > 1"
                            class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full p-1.5 transition-colors"
                            @click="prevPhoto"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button
                            v-if="photos.length > 1"
                            class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full p-1.5 transition-colors"
                            @click="nextPhoto"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>

                        <!-- Bouton supprimer (uploader ou enseignant) -->
                        <button
                            v-if="peutSupprimerMedia(photos[photoIndex])"
                            class="absolute top-2 right-2 bg-destructive/80 hover:bg-destructive text-white rounded-full p-1.5 transition-colors"
                            @click="deleteMedia(photos[photoIndex])"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Légende -->
                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ photos[photoIndex].nom_original }}</span>
                        <span>par {{ photos[photoIndex].auteur.prenom }} {{ photos[photoIndex].auteur.nom }}</span>
                    </div>

                    <!-- Miniatures -->
                    <div v-if="photos.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="(photo, idx) in photos"
                            :key="photo.id"
                            class="shrink-0 h-14 w-14 rounded overflow-hidden border-2 transition-colors"
                            :class="idx === photoIndex ? 'border-primary' : 'border-transparent'"
                            @click="photoIndex = idx"
                        >
                            <img
                                :src="photo.url"
                                :alt="photo.nom_original"
                                class="h-full w-full object-cover"
                            />
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Documents du groupe -->
            <Card v-if="documents.length > 0">
                <CardHeader>
                    <CardTitle>Documents</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col divide-y">
                        <div
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <FileText class="text-muted-foreground h-5 w-5 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.nom_original }}</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ doc.type.toUpperCase() }} · {{ formatSize(doc.taille) }} ·
                                        <span>{{ doc.auteur.prenom }} {{ doc.auteur.nom }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="doc.url" target="_blank" download>
                                        <Download class="h-4 w-4" />
                                    </a>
                                </Button>
                                <Button
                                    v-if="peutSupprimerMedia(doc)"
                                    size="sm"
                                    variant="destructive"
                                    @click="deleteMedia(doc)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Fichiers audio -->
            <Card v-if="audios.length > 0">
                <CardHeader>
                    <CardTitle>Audio</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col divide-y">
                        <div
                            v-for="audio in audios"
                            :key="audio.id"
                            class="flex flex-col gap-2 py-3"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <Music class="text-muted-foreground h-5 w-5 shrink-0" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ audio.nom_original }}</p>
                                        <p class="text-muted-foreground text-xs">
                                            {{ formatSize(audio.taille) }} ·
                                            <span>{{ audio.auteur.prenom }} {{ audio.auteur.nom }}</span>
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    v-if="peutSupprimerMedia(audio)"
                                    size="sm"
                                    variant="destructive"
                                    @click="deleteMedia(audio)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                            <audio controls class="w-full h-10">
                                <source :src="audio.url" />
                                Votre navigateur ne supporte pas la lecture audio.
                            </audio>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Zone upload (membres seulement) -->
            <Card v-if="estMembre">
                <CardHeader>
                    <CardTitle>Ajouter des fichiers</CardTitle>
                </CardHeader>
                <CardContent>
                    <input
                        ref="mediaFileInput"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.mp3,.wav,.ogg,.m4a,.aac"
                        class="hidden"
                        @change="handleMediaChange"
                    />
                    <p v-if="mediaForm.errors.fichier" class="text-destructive mb-3 text-sm">
                        {{ mediaForm.errors.fichier }}
                    </p>
                    <div
                        class="border-2 border-dashed rounded-lg p-8 flex flex-col items-center gap-3 cursor-pointer hover:bg-muted/50 transition-colors"
                        @click="mediaFileInput?.click()"
                    >
                        <ImagePlus class="text-muted-foreground h-8 w-8" />
                        <div class="text-center">
                            <p class="text-sm font-medium">
                                {{ mediaForm.processing ? 'Envoi en cours…' : 'Cliquez pour ajouter un fichier' }}
                            </p>
                            <p class="text-muted-foreground text-xs mt-1">
                                Photos (JPG, PNG, WEBP), documents (PDF, DOCX) ou audio (MP3, WAV, M4A) · max 50 Mo
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Notes -->
            <Card>
                <CardHeader>
                    <CardTitle>
                        Notes
                        <span class="text-muted-foreground ml-2 text-sm font-normal">
                            ({{ groupe.notes.length }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <!-- Liste des notes -->
                    <div v-if="groupe.notes.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                        Aucune note pour l'instant.
                    </div>

                    <div
                        v-for="note in groupe.notes"
                        :key="note.id"
                        class="border-b pb-4 last:border-0 last:pb-0"
                    >
                        <div class="mb-1 flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">
                                    {{ note.auteur.prenom }} {{ note.auteur.nom }}
                                </span>
                                <span class="text-muted-foreground text-xs">
                                    {{ formatDate(note.created_at) }}
                                </span>
                            </div>
                            <Button
                                v-if="note.user_id === userId"
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:text-destructive h-7 w-7 p-0"
                                @click="deleteNote(note)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <p class="text-sm whitespace-pre-wrap">{{ note.contenu }}</p>
                    </div>

                    <!-- Formulaire nouvelle note (membres seulement) -->
                    <template v-if="estMembre">
                        <div class="border-t pt-4">
                            <form class="flex flex-col gap-2" @submit.prevent="submitNote">
                                <textarea
                                    v-model="noteForm.contenu"
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Écrire une note…"
                                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <p v-if="noteForm.errors.contenu" class="text-destructive text-sm">
                                    {{ noteForm.errors.contenu }}
                                </p>
                                <div class="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="noteForm.processing || !noteForm.contenu.trim()"
                                    >
                                        Publier
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
        <!-- Dialog : gérer les membres -->
        <Dialog v-model:open="showMembresDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Gérer les membres</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitMembres">

                    <!-- Inviter des étudiants -->
                    <div>
                        <p class="text-sm font-medium mb-2">Inviter des étudiants</p>
                        <div v-if="etudiantsDispo.length === 0" class="text-muted-foreground text-sm">
                            Tous les étudiants de la classe sont déjà membres.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="etudiant in etudiantsDispo"
                                :key="etudiant.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :id="`ajouter-${etudiant.id}`"
                                    :checked="membresForm.ajouter.includes(etudiant.id)"
                                    @update:checked="(val) => toggleAjouter(etudiant.id, val)"
                                />
                                <Label :for="`ajouter-${etudiant.id}`" class="cursor-pointer font-normal">
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <!-- Retirer des membres -->
                    <div>
                        <p class="text-sm font-medium mb-2">Retirer des membres</p>
                        <div class="space-y-2">
                            <div
                                v-for="membre in groupe.membres.filter(m => m.id !== userId)"
                                :key="membre.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :id="`retirer-${membre.id}`"
                                    :checked="membresForm.retirer.includes(membre.id)"
                                    @update:checked="(val) => toggleRetirer(membre.id, val)"
                                />
                                <Label :for="`retirer-${membre.id}`" class="cursor-pointer font-normal">
                                    {{ membre.prenom }} {{ membre.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showMembresDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="membresForm.processing">
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog : modifier les thématiques -->
        <Dialog v-model:open="showThematiquesDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Modifier les thématiques</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitThematiques">
                    <p class="text-muted-foreground text-sm">
                        Sélectionnez de 0 à 3 thématiques pour votre groupe.
                        <span class="font-medium">
                            ({{ thematiquesForm.thematiques.length }}/3)
                        </span>
                    </p>

                    <div v-if="thematiquesDispo.length === 0" class="text-muted-foreground text-sm">
                        Aucune thématique disponible pour ce cours.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="thematique in thematiquesDispo"
                            :key="thematique.id"
                            class="flex items-start gap-3"
                        >
                            <Checkbox
                                :id="`t-${thematique.id}`"
                                :checked="thematiquesForm.thematiques.includes(thematique.id)"
                                :disabled="thematiquesMax && !thematiquesForm.thematiques.includes(thematique.id)"
                                @update:checked="(val) => toggleThematique(thematique.id, val)"
                            />
                            <Label
                                :for="`t-${thematique.id}`"
                                class="cursor-pointer font-normal leading-snug"
                                :class="{ 'text-muted-foreground': thematiquesMax && !thematiquesForm.thematiques.includes(thematique.id) }"
                            >
                                {{ thematique.nom }}
                                <span
                                    v-if="thematique.periode_historique"
                                    class="text-muted-foreground ml-1 text-xs"
                                >
                                    — {{ thematique.periode_historique }}
                                </span>
                            </Label>
                        </div>
                    </div>

                    <p v-if="thematiquesForm.errors.thematiques" class="text-destructive text-sm">
                        {{ thematiquesForm.errors.thematiques }}
                    </p>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showThematiquesDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="thematiquesForm.processing">
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
