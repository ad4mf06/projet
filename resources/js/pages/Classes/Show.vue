<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileText, Pencil, Plus, Trash2, Upload, Users } from 'lucide-vue-next';
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

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    no_da: string;
    statut_cours: string | null;
};

type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
};

type Groupe = {
    id: number;
    nom: string;
    created_by: number;
    membres: Membre[];
    thematiques: Thematique[];
    createur: Membre;
};

type Document = {
    id: number;
    nom_original: string;
    type: string;
    taille: number;
    url: string;
};

type Classe = {
    id: number;
    nom_cours: string;
    description: string | null;
    heures_par_semaine: string;
    code: string;
    groupe: string;
};

type Props = {
    classe: Classe;
    etudiants: Etudiant[];
    groupes: Groupe[];
    documents: Document[];
};

const props = defineProps<Props>();

// ─── Ajouter un étudiant ──────────────────────────────────────────────────────
const showAddDialog = ref(false);
const addForm = useForm({
    prenom: '',
    nom: '',
    no_da: '',
    statut_cours: '',
    email: '',
});

function openAdd() {
    addForm.reset();
    showAddDialog.value = true;
}

function submitAdd() {
    addForm.post(`/classes/${props.classe.id}/etudiants`, {
        onSuccess: () => {
            showAddDialog.value = false;
            addForm.reset();
        },
    });
}

// ─── Modifier un étudiant ─────────────────────────────────────────────────────
const showEditDialog = ref(false);
const editingEtudiantId = ref<number | null>(null);
const editForm = useForm({
    prenom: '',
    nom: '',
    email: '',
    no_da: '',
    statut_cours: '',
});

function openEdit(etudiant: Etudiant) {
    editingEtudiantId.value = etudiant.id;
    editForm.prenom = etudiant.prenom;
    editForm.nom = etudiant.nom;
    editForm.email = etudiant.email;
    editForm.no_da = etudiant.no_da;
    editForm.statut_cours = etudiant.statut_cours ?? '';
    showEditDialog.value = true;
}

function submitEdit() {
    if (!editingEtudiantId.value) return;
    editForm.put(`/classes/${props.classe.id}/etudiants/${editingEtudiantId.value}`, {
        onSuccess: () => {
            showEditDialog.value = false;
        },
    });
}

// ─── Retirer un étudiant ──────────────────────────────────────────────────────
const deleteForm = useForm({});

function removeEtudiant(etudiant: Etudiant) {
    if (!confirm(`Retirer ${etudiant.prenom} ${etudiant.nom} de la classe ?`)) return;
    deleteForm.delete(`/classes/${props.classe.id}/etudiants/${etudiant.id}`);
}

// ─── Import CSV ───────────────────────────────────────────────────────────────
const showImportDialog = ref(false);
const importForm = useForm({ csv: null as File | null });

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        importForm.csv = input.files[0];
    }
}

function submitImport() {
    importForm.post(`/classes/${props.classe.id}/import`, {
        onSuccess: () => {
            showImportDialog.value = false;
            importForm.reset();
        },
    });
}

// ─── Documents ────────────────────────────────────────────────────────────────
const docFileInput = ref<HTMLInputElement | null>(null);
const docForm = useForm({ document: null as File | null });

function handleDocChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        docForm.document = input.files[0];
        docForm.post(`/classes/${props.classe.id}/documents`, {
            onSuccess: () => {
                docForm.reset();
                if (docFileInput.value) docFileInput.value.value = '';
            },
        });
    }
}

const deleteDocForm = useForm({});

function removeDocument(doc: Document) {
    if (!confirm(`Supprimer "${doc.nom_original}" ?`)) return;
    deleteDocForm.delete(`/classes/${props.classe.id}/documents/${doc.id}`);
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
    <AppLayout>
        <Head :title="`${classe.code} — ${classe.nom_cours}`" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Breadcrumb retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/enseignant">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à mon espace
                    </Link>
                </Button>
            </div>

            <!-- En-tête de la classe -->
            <div class="flex flex-col gap-1">
                <Heading
                    :title="`${classe.code} — Groupe ${classe.groupe}`"
                    :description="classe.nom_cours"
                />
                <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                    <span>{{ classe.heures_par_semaine }} h/sem</span>
                    <span v-if="classe.description">{{ classe.description }}</span>
                </div>
            </div>

            <!-- Liste des étudiants -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        Étudiants
                        <span class="text-muted-foreground ml-2 text-sm font-normal">
                            ({{ etudiants.length }})
                        </span>
                    </CardTitle>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" @click="showImportDialog = true">
                            <Upload class="mr-2 h-4 w-4" />
                            Importer CSV
                        </Button>
                        <Button size="sm" @click="openAdd">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">No DA</th>
                                    <th class="pb-3 pr-4 font-medium">Nom</th>
                                    <th class="pb-3 pr-4 font-medium">Prénom</th>
                                    <th class="pb-3 pr-4 font-medium">Courriel</th>
                                    <th class="pb-3 pr-4 font-medium">Statut</th>
                                    <th class="pb-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="etudiant in etudiants"
                                    :key="etudiant.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-mono text-xs">{{ etudiant.no_da }}</td>
                                    <td class="py-3 pr-4 font-medium">{{ etudiant.nom }}</td>
                                    <td class="py-3 pr-4">{{ etudiant.prenom }}</td>
                                    <td class="text-muted-foreground py-3 pr-4 text-xs">{{ etudiant.email }}</td>
                                    <td class="py-3 pr-4">
                                        <span
                                            v-if="etudiant.statut_cours"
                                            class="bg-muted rounded px-2 py-0.5 text-xs"
                                        >
                                            {{ etudiant.statut_cours }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="py-3">
                                        <div class="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openEdit(etudiant)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="removeEtudiant(etudiant)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="etudiants.length === 0">
                                    <td colspan="6" class="text-muted-foreground py-6 text-center">
                                        Aucun étudiant dans cette classe.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Groupes des étudiants -->
            <Card>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            Groupes
                            <span class="text-muted-foreground text-sm font-normal">
                                ({{ groupes.length }})
                            </span>
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="groupes.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                        Aucun groupe créé pour l'instant.
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="groupe in groupes"
                            :key="groupe.id"
                            class="border rounded-lg p-4 flex flex-col gap-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-sm">{{ groupe.nom }}</p>
                                <Button size="sm" variant="outline" as-child>
                                    <Link :href="`/classes/${classe.id}/groupes/${groupe.id}`">
                                        Voir
                                    </Link>
                                </Button>
                            </div>

                            <!-- Membres -->
                            <div>
                                <p class="text-muted-foreground text-xs font-medium mb-1">Membres</p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="membre in groupe.membres"
                                        :key="membre.id"
                                        class="bg-muted rounded-full px-2 py-0.5 text-xs"
                                    >
                                        {{ membre.prenom }} {{ membre.nom }}
                                    </span>
                                </div>
                            </div>

                            <!-- Thématiques -->
                            <div v-if="groupe.thematiques.length > 0">
                                <p class="text-muted-foreground text-xs font-medium mb-1">Thématiques</p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="thematique in groupe.thematiques"
                                        :key="thematique.id"
                                        class="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-xs"
                                    >
                                        {{ thematique.nom }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Documents de la classe -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Documents
                            <span class="text-muted-foreground text-sm font-normal">
                                ({{ documents.length }})
                            </span>
                        </span>
                    </CardTitle>
                    <div>
                        <input
                            ref="docFileInput"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            class="hidden"
                            @change="handleDocChange"
                        />
                        <Button
                            size="sm"
                            :disabled="docForm.processing"
                            @click="docFileInput?.click()"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            Ajouter un document
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <p v-if="docForm.errors.document" class="text-destructive mb-3 text-sm">
                        {{ docForm.errors.document }}
                    </p>

                    <div v-if="documents.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                        Aucun document dans cette classe.
                    </div>

                    <div v-else class="flex flex-col divide-y">
                        <div
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <FileText class="text-muted-foreground h-5 w-5 shrink-0" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ doc.nom_original }}</p>
                                    <p class="text-muted-foreground text-xs uppercase">
                                        {{ doc.type }} · {{ formatSize(doc.taille) }}
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
                                    size="sm"
                                    variant="destructive"
                                    @click="removeDocument(doc)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Ajouter étudiant -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter un étudiant</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="add-prenom">Prénom</Label>
                            <Input id="add-prenom" v-model="addForm.prenom" placeholder="Prénom" />
                            <InputError :message="addForm.errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="add-nom">Nom</Label>
                            <Input id="add-nom" v-model="addForm.nom" placeholder="Nom de famille" />
                            <InputError :message="addForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-da">No DA</Label>
                        <Input id="add-da" v-model="addForm.no_da" placeholder="Numéro DA" />
                        <InputError :message="addForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-statut">Statut du cours</Label>
                        <Input id="add-statut" v-model="addForm.statut_cours" placeholder="Statut (optionnel)" />
                        <InputError :message="addForm.errors.statut_cours" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-email">
                            Courriel
                            <span class="text-muted-foreground text-xs font-normal">
                                (auto-généré si vide)
                            </span>
                        </Label>
                        <Input
                            id="add-email"
                            v-model="addForm.email"
                            type="email"
                            placeholder="prenom.nom@etu.cegepdrummond.ca"
                        />
                        <InputError :message="addForm.errors.email" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showAddDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="addForm.processing">Ajouter</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Modifier étudiant -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier l'étudiant</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Prénom</Label>
                            <Input v-model="editForm.prenom" placeholder="Prénom" />
                            <InputError :message="editForm.errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Nom</Label>
                            <Input v-model="editForm.nom" placeholder="Nom de famille" />
                            <InputError :message="editForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Courriel</Label>
                        <Input v-model="editForm.email" type="email" />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>No DA</Label>
                        <Input v-model="editForm.no_da" />
                        <InputError :message="editForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Statut du cours</Label>
                        <Input v-model="editForm.statut_cours" placeholder="Statut (optionnel)" />
                        <InputError :message="editForm.errors.statut_cours" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Import CSV -->
        <Dialog v-model:open="showImportDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Importer des étudiants (CSV)</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitImport">
                    <p class="text-muted-foreground text-sm">
                        Format attendu (séparateur <code>;</code>) :
                    </p>
                    <code class="bg-muted block rounded p-3 text-xs">
                        No de DA;Nom de l'étudiant;Prénom de l'étudiant;Statut du cours
                    </code>
                    <div class="grid gap-2">
                        <Label for="csv-file">Fichier CSV</Label>
                        <Input
                            id="csv-file"
                            type="file"
                            accept=".csv,.txt"
                            @change="handleFileChange"
                        />
                        <InputError :message="importForm.errors.csv" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showImportDialog = false">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="importForm.processing || !importForm.csv">
                            Importer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
