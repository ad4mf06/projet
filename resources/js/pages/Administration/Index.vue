<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
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

type Enseignant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    classes_count: number;
    thematiques_count: number;
};

type Stats = {
    total_enseignants: number;
    total_classes: number;
    total_etudiants: number;
};

type Props = {
    enseignants: Enseignant[];
    stats: Stats;
};

const props = defineProps<Props>();

// ─── Créer un enseignant ──────────────────────────────────────────────────────
const showCreateDialog = ref(false);
const createForm = useForm({
    prenom: '',
    nom: '',
    email: '',
});

function openCreate() {
    createForm.reset();
    showCreateDialog.value = true;
}

function submitCreate() {
    createForm.post('/administration/enseignants', {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
}

// ─── Modifier un enseignant ───────────────────────────────────────────────────
const showEditDialog = ref(false);
const editingId = ref<number | null>(null);
const editForm = useForm({
    prenom: '',
    nom: '',
    email: '',
});

function openEdit(enseignant: Enseignant) {
    editingId.value = enseignant.id;
    editForm.prenom = enseignant.prenom;
    editForm.nom = enseignant.nom;
    editForm.email = enseignant.email;
    showEditDialog.value = true;
}

function submitEdit() {
    if (!editingId.value) return;
    editForm.put(`/administration/enseignants/${editingId.value}`, {
        onSuccess: () => {
            showEditDialog.value = false;
        },
    });
}

// ─── Supprimer un enseignant ──────────────────────────────────────────────────
const deleteForm = useForm({});

function deleteEnseignant(enseignant: Enseignant) {
    if (!confirm(`Supprimer l'enseignant ${enseignant.prenom} ${enseignant.nom} ?`)) return;
    deleteForm.delete(`/administration/enseignants/${enseignant.id}`);
}
</script>

<template>
    <AppLayout>
        <Head title="Administration" />

        <div class="flex flex-col gap-6 p-6">
            <Heading title="Administration" description="Gestion de la plateforme" />

            <!-- Statistiques -->
            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Enseignants</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_enseignants }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Classes</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_classes }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Étudiants</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_etudiants }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Liste des enseignants -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Enseignants</CardTitle>
                    <Button size="sm" @click="openCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter un enseignant
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">Prénom</th>
                                    <th class="pb-3 pr-4 font-medium">Nom</th>
                                    <th class="pb-3 pr-4 font-medium">Courriel</th>
                                    <th class="pb-3 pr-4 font-medium text-center">Classes</th>
                                    <th class="pb-3 pr-4 font-medium text-center">Thématiques</th>
                                    <th class="pb-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="enseignant in enseignants"
                                    :key="enseignant.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4">{{ enseignant.prenom }}</td>
                                    <td class="py-3 pr-4">{{ enseignant.nom }}</td>
                                    <td class="py-3 pr-4 text-muted-foreground">{{ enseignant.email }}</td>
                                    <td class="py-3 pr-4 text-center">{{ enseignant.classes_count }}</td>
                                    <td class="py-3 pr-4 text-center">{{ enseignant.thematiques_count }}</td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openEdit(enseignant)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteEnseignant(enseignant)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="enseignants.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        Aucun enseignant pour l'instant.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Créer enseignant -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter un enseignant</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="grid gap-2">
                        <Label for="create-prenom">Prénom</Label>
                        <Input id="create-prenom" v-model="createForm.prenom" placeholder="Prénom" />
                        <InputError :message="createForm.errors.prenom" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-nom">Nom</Label>
                        <Input id="create-nom" v-model="createForm.nom" placeholder="Nom de famille" />
                        <InputError :message="createForm.errors.nom" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-email">Courriel</Label>
                        <Input
                            id="create-email"
                            v-model="createForm.email"
                            type="email"
                            placeholder="courriel@exemple.com"
                        />
                        <InputError :message="createForm.errors.email" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="createForm.processing">
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Modifier enseignant -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier l'enseignant</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <Label for="edit-prenom">Prénom</Label>
                        <Input id="edit-prenom" v-model="editForm.prenom" placeholder="Prénom" />
                        <InputError :message="editForm.errors.prenom" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-nom">Nom</Label>
                        <Input id="edit-nom" v-model="editForm.nom" placeholder="Nom de famille" />
                        <InputError :message="editForm.errors.nom" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-email">Courriel</Label>
                        <Input
                            id="edit-email"
                            v-model="editForm.email"
                            type="email"
                            placeholder="courriel@exemple.com"
                        />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
