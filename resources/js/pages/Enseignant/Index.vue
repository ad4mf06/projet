<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BookOpen, ExternalLink, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    description: string | null;
    heures_par_semaine: string;
    code: string;
    groupe: string;
    etudiants_count: number;
};

type Thematique = {
    id: number;
    nom: string;
    description: string | null;
    periode_historique: string | null;
};

type Props = {
    classes: Classe[];
    thematiques: Thematique[];
};

const props = defineProps<Props>();

// ─── Classes ──────────────────────────────────────────────────────────────────
const showCreateClasseDialog = ref(false);
const showEditClasseDialog = ref(false);
const editingClasseId = ref<number | null>(null);

const classeForm = useForm({
    nom_cours: '',
    description: '',
    heures_par_semaine: '',
    code: '',
    groupe: '',
});

function openCreateClasse() {
    classeForm.reset();
    showCreateClasseDialog.value = true;
}

function submitCreateClasse() {
    classeForm.post('/classes', {
        onSuccess: () => {
            showCreateClasseDialog.value = false;
            classeForm.reset();
        },
    });
}

function openEditClasse(classe: Classe) {
    editingClasseId.value = classe.id;
    classeForm.nom_cours = classe.nom_cours;
    classeForm.description = classe.description ?? '';
    classeForm.heures_par_semaine = classe.heures_par_semaine;
    classeForm.code = classe.code;
    classeForm.groupe = classe.groupe;
    showEditClasseDialog.value = true;
}

function submitEditClasse() {
    if (!editingClasseId.value) return;
    classeForm.put(`/classes/${editingClasseId.value}`, {
        onSuccess: () => {
            showEditClasseDialog.value = false;
        },
    });
}

const deleteClasseForm = useForm({});

function deleteClasse(classe: Classe) {
    if (!confirm(`Supprimer la classe « ${classe.nom_cours} » ?`)) return;
    deleteClasseForm.delete(`/classes/${classe.id}`);
}

// ─── Thématiques ──────────────────────────────────────────────────────────────
const showCreateThematiqueDialog = ref(false);
const showEditThematiqueDialog = ref(false);
const editingThematiqueId = ref<number | null>(null);

const thematiqueForm = useForm({
    nom: '',
    description: '',
    periode_historique: '',
});

function openCreateThematique() {
    thematiqueForm.reset();
    showCreateThematiqueDialog.value = true;
}

function submitCreateThematique() {
    thematiqueForm.post('/thematiques', {
        onSuccess: () => {
            showCreateThematiqueDialog.value = false;
            thematiqueForm.reset();
        },
    });
}

function openEditThematique(thematique: Thematique) {
    editingThematiqueId.value = thematique.id;
    thematiqueForm.nom = thematique.nom;
    thematiqueForm.description = thematique.description ?? '';
    thematiqueForm.periode_historique = thematique.periode_historique ?? '';
    showEditThematiqueDialog.value = true;
}

function submitEditThematique() {
    if (!editingThematiqueId.value) return;
    thematiqueForm.put(`/thematiques/${editingThematiqueId.value}`, {
        onSuccess: () => {
            showEditThematiqueDialog.value = false;
        },
    });
}

const deleteThematiqueForm = useForm({});

function deleteThematique(thematique: Thematique) {
    if (!confirm(`Supprimer la thématique « ${thematique.nom} » ?`)) return;
    deleteThematiqueForm.delete(`/thematiques/${thematique.id}`);
}
</script>

<template>
    <AppLayout>
        <Head title="Espace enseignant" />

        <div class="flex flex-col gap-6 p-6">
            <Heading title="Mon espace" description="Gérez vos classes et vos thématiques" />

            <!-- ─── Mes classes ─────────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Mes classes</CardTitle>
                    <Button size="sm" @click="openCreateClasse">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle classe
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">Code</th>
                                    <th class="pb-3 pr-4 font-medium">Groupe</th>
                                    <th class="pb-3 pr-4 font-medium">Nom du cours</th>
                                    <th class="pb-3 pr-4 font-medium text-center">H/sem</th>
                                    <th class="pb-3 pr-4 font-medium text-center">Étudiants</th>
                                    <th class="pb-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="classe in classes"
                                    :key="classe.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-mono text-xs">{{ classe.code }}</td>
                                    <td class="py-3 pr-4 font-mono text-xs">{{ classe.groupe }}</td>
                                    <td class="py-3 pr-4">{{ classe.nom_cours }}</td>
                                    <td class="py-3 pr-4 text-center">{{ classe.heures_par_semaine }}</td>
                                    <td class="py-3 pr-4 text-center">{{ classe.etudiants_count }}</td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button size="sm" variant="outline" as-child>
                                                <Link :href="`/classes/${classe.id}`">
                                                    <ExternalLink class="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openEditClasse(classe)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteClasse(classe)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="classes.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        Aucune classe créée. Cliquez sur « Nouvelle classe » pour commencer.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Mes thématiques ────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Mes thématiques
                        </div>
                    </CardTitle>
                    <Button size="sm" @click="openCreateThematique">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle thématique
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">Nom</th>
                                    <th class="pb-3 pr-4 font-medium">Période historique</th>
                                    <th class="pb-3 pr-4 font-medium">Description</th>
                                    <th class="pb-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="thematique in thematiques"
                                    :key="thematique.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">{{ thematique.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">
                                        {{ thematique.periode_historique ?? '—' }}
                                    </td>
                                    <td class="text-muted-foreground max-w-xs truncate py-3 pr-4">
                                        {{ thematique.description ?? '—' }}
                                    </td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openEditThematique(thematique)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteThematique(thematique)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="thematiques.length === 0">
                                    <td colspan="4" class="text-muted-foreground py-6 text-center">
                                        Aucune thématique créée.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Créer classe -->
        <Dialog v-model:open="showCreateClasseDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Créer une classe</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreateClasse">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="code">Code de cours</Label>
                            <Input id="code" v-model="classeForm.code" placeholder="420-6N1-DM" />
                            <InputError :message="classeForm.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="groupe">Groupe</Label>
                            <Input id="groupe" v-model="classeForm.groupe" placeholder="00001" />
                            <InputError :message="classeForm.errors.groupe" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="nom_cours">Nom du cours</Label>
                        <Input id="nom_cours" v-model="classeForm.nom_cours" placeholder="Nom du cours" />
                        <InputError :message="classeForm.errors.nom_cours" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="heures">Heures par semaine</Label>
                        <Input
                            id="heures"
                            v-model="classeForm.heures_par_semaine"
                            type="number"
                            step="0.5"
                            min="0.5"
                            placeholder="3"
                        />
                        <InputError :message="classeForm.errors.heures_par_semaine" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="classeForm.description" placeholder="Description (optionnel)" />
                        <InputError :message="classeForm.errors.description" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateClasseDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="classeForm.processing">Créer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Modifier classe -->
        <Dialog v-model:open="showEditClasseDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la classe</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEditClasse">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Code de cours</Label>
                            <Input v-model="classeForm.code" placeholder="420-6N1-DM" />
                            <InputError :message="classeForm.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Groupe</Label>
                            <Input v-model="classeForm.groupe" placeholder="00001" />
                            <InputError :message="classeForm.errors.groupe" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Nom du cours</Label>
                        <Input v-model="classeForm.nom_cours" placeholder="Nom du cours" />
                        <InputError :message="classeForm.errors.nom_cours" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Heures par semaine</Label>
                        <Input
                            v-model="classeForm.heures_par_semaine"
                            type="number"
                            step="0.5"
                            min="0.5"
                        />
                        <InputError :message="classeForm.errors.heures_par_semaine" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Description</Label>
                        <Input v-model="classeForm.description" placeholder="Description (optionnel)" />
                        <InputError :message="classeForm.errors.description" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditClasseDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="classeForm.processing">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Créer thématique -->
        <Dialog v-model:open="showCreateThematiqueDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Créer une thématique</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreateThematique">
                    <div class="grid gap-2">
                        <Label for="nom-theme">Nom</Label>
                        <Input id="nom-theme" v-model="thematiqueForm.nom" placeholder="Ex: La pêche, L'agriculture..." />
                        <InputError :message="thematiqueForm.errors.nom" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="periode">Période historique</Label>
                        <Input id="periode" v-model="thematiqueForm.periode_historique" placeholder="Ex: 1700–1850" />
                        <InputError :message="thematiqueForm.errors.periode_historique" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="desc-theme">Description</Label>
                        <Input id="desc-theme" v-model="thematiqueForm.description" placeholder="Brève description" />
                        <InputError :message="thematiqueForm.errors.description" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateThematiqueDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="thematiqueForm.processing">Créer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Modifier thématique -->
        <Dialog v-model:open="showEditThematiqueDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la thématique</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEditThematique">
                    <div class="grid gap-2">
                        <Label>Nom</Label>
                        <Input v-model="thematiqueForm.nom" placeholder="Ex: La pêche..." />
                        <InputError :message="thematiqueForm.errors.nom" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Période historique</Label>
                        <Input v-model="thematiqueForm.periode_historique" placeholder="Ex: 1700–1850" />
                        <InputError :message="thematiqueForm.errors.periode_historique" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Description</Label>
                        <Input v-model="thematiqueForm.description" placeholder="Brève description" />
                        <InputError :message="thematiqueForm.errors.description" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditThematiqueDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="thematiqueForm.processing">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
