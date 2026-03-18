<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileText, Plus, Trash2, Users } from 'lucide-vue-next';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
    periode_historique: string | null;
};

type Groupe = {
    id: number;
    nom: string;
    created_by: number;
    membres: Etudiant[];
    thematiques: Thematique[];
};

type Document = {
    id: number;
    nom_original: string;
    type: string;
    taille: number;
    url: string;
};

type Props = {
    classe: Classe;
    monGroupe: Groupe | null;
    autresEtudiants: Etudiant[];
    thematiques: Thematique[];
    documents: Document[];
};

function formatTaille(octets: number): string {
    if (octets < 1024) return `${octets} o`;
    if (octets < 1024 * 1024) return `${(octets / 1024).toFixed(1)} Ko`;
    return `${(octets / (1024 * 1024)).toFixed(1)} Mo`;
}

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as any).user?.id);

// ─── Créer un groupe ──────────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const form = useForm({
    nom: '',
    membres: [] as number[],
    thematiques: [] as number[],
});

function openCreate() {
    form.reset();
    showCreateDialog.value = true;
}

function toggleMembre(id: number, val: boolean | string) {
    if (val) {
        if (!form.membres.includes(id)) form.membres.push(id);
    } else {
        form.membres = form.membres.filter((m) => m !== id);
    }
}

const thematiquesMax = computed(() => form.thematiques.length >= 3);

function toggleThematique(id: number, val: boolean | string) {
    if (val) {
        if (form.thematiques.length < 3 && !form.thematiques.includes(id)) {
            form.thematiques.push(id);
        }
    } else {
        form.thematiques = form.thematiques.filter((t) => t !== id);
    }
}

function submitCreate() {
    form.post(`/classes/${props.classe.id}/groupes`, {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
        },
    });
}

// ─── Supprimer le groupe ──────────────────────────────────────────────────────
const deleteForm = useForm({});

function deleteGroupe() {
    if (!props.monGroupe) return;
    if (!confirm(`Supprimer le groupe "${props.monGroupe.nom}" ?`)) return;
    deleteForm.delete(`/classes/${props.classe.id}/groupes/${props.monGroupe.id}`);
}
</script>

<template>
    <AppLayout>
        <Head :title="`Groupes — ${classe.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/classes">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à mes classes
                    </Link>
                </Button>
            </div>

            <!-- Heading -->
            <Heading
                :title="`Groupes — ${classe.nom_cours}`"
                :description="`${classe.code} — Groupe ${classe.groupe}`"
            />

            <!-- Mon groupe -->
            <template v-if="monGroupe">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            {{ monGroupe.nom }}
                        </CardTitle>
                        <div class="flex gap-2">
                            <Button size="sm" as-child>
                                <Link :href="`/classes/${props.classe.id}/groupes/${monGroupe.id}`">Accéder</Link>
                            </Button>
                            <Button
                                v-if="monGroupe.created_by === userId"
                                size="sm"
                                variant="destructive"
                                @click="deleteGroupe"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Supprimer
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <!-- Membres -->
                        <div>
                            <p class="text-muted-foreground mb-2 text-sm font-medium">Membres</p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="membre in monGroupe.membres"
                                    :key="membre.id"
                                    class="bg-muted rounded-full px-3 py-1 text-sm"
                                >
                                    {{ membre.prenom }} {{ membre.nom }}
                                </span>
                            </div>
                        </div>

                        <!-- Thématiques -->
                        <div v-if="monGroupe.thematiques.length > 0">
                            <p class="text-muted-foreground mb-2 text-sm font-medium">Thématiques</p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="thematique in monGroupe.thematiques"
                                    :key="thematique.id"
                                    class="bg-primary/10 text-primary rounded-full px-3 py-1 text-sm"
                                >
                                    {{ thematique.nom }}
                                    <span
                                        v-if="thematique.periode_historique"
                                        class="text-muted-foreground ml-1 text-xs"
                                    >
                                        ({{ thematique.periode_historique }})
                                    </span>
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Pas encore de groupe -->
            <template v-else>
                <div class="flex flex-col items-center gap-4 py-12">
                    <p class="text-muted-foreground text-center">
                        Vous n'avez pas encore de groupe dans cette classe.
                    </p>
                    <Button @click="openCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        Créer un groupe
                    </Button>
                </div>
            </template>

            <!-- Documents de la classe -->
            <Card v-if="documents.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Documents du cours
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <FileText class="text-muted-foreground h-4 w-4 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.nom_original }}</p>
                                    <p class="text-muted-foreground text-xs">{{ formatTaille(doc.taille) }}</p>
                                </div>
                            </div>
                            <a :href="doc.url" target="_blank" download>
                                <Button size="sm" variant="ghost">
                                    <Download class="h-4 w-4" />
                                </Button>
                            </a>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- Dialog création de groupe -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Créer un groupe</DialogTitle>
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submitCreate">
                    <!-- Nom du groupe -->
                    <div class="grid gap-2">
                        <Label for="groupe-nom">Nom du groupe</Label>
                        <Input
                            id="groupe-nom"
                            v-model="form.nom"
                            placeholder="Ex: Les explorateurs"
                        />
                        <p v-if="form.errors.nom" class="text-destructive text-sm">
                            {{ form.errors.nom }}
                        </p>
                    </div>

                    <!-- Membres -->
                    <div v-if="autresEtudiants.length > 0" class="grid gap-2">
                        <Label>Membres à inviter</Label>
                        <div class="space-y-2">
                            <div
                                v-for="etudiant in autresEtudiants"
                                :key="etudiant.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`membre-${etudiant.id}`"
                                    :checked="form.membres.includes(etudiant.id)"
                                    @update:checked="(val) => toggleMembre(etudiant.id, val)"
                                />
                                <Label :for="`membre-${etudiant.id}`" class="cursor-pointer font-normal">
                                    {{ etudiant.prenom }} {{ etudiant.nom }}
                                </Label>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                        Aucun autre étudiant dans cette classe.
                    </p>

                    <!-- Thématiques -->
                    <div v-if="thematiques.length > 0" class="grid gap-2">
                        <Label>
                            Thématiques
                            <span class="text-muted-foreground text-xs font-normal">
                                (max 3 — {{ form.thematiques.length }}/3 sélectionnée{{ form.thematiques.length > 1 ? 's' : '' }})
                            </span>
                        </Label>
                        <div class="space-y-2">
                            <div
                                v-for="thematique in thematiques"
                                :key="thematique.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`thematique-${thematique.id}`"
                                    :checked="form.thematiques.includes(thematique.id)"
                                    :disabled="thematiquesMax && !form.thematiques.includes(thematique.id)"
                                    @update:checked="(val) => toggleThematique(thematique.id, val)"
                                />
                                <Label
                                    :for="`thematique-${thematique.id}`"
                                    class="cursor-pointer font-normal"
                                    :class="{ 'text-muted-foreground': thematiquesMax && !form.thematiques.includes(thematique.id) }"
                                >
                                    {{ thematique.nom }}
                                    <span
                                        v-if="thematique.periode_historique"
                                        class="text-muted-foreground text-xs"
                                    >
                                        — {{ thematique.periode_historique }}
                                    </span>
                                </Label>
                            </div>
                        </div>
                        <p v-if="form.errors.thematiques" class="text-destructive text-sm">
                            {{ form.errors.thematiques }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="form.processing || !form.nom.trim()">
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
