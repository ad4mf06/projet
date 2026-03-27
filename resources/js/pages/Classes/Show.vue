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
import { useI18n } from 'vue-i18n';

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
    numero: number;
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
const { t } = useI18n();

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
    if (!confirm(t('classes.show.confirm_remove_student', { prenom: etudiant.prenom, nom: etudiant.nom }))) return;
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
    if (!confirm(t('classes.show.confirm_delete_document', { nom: doc.nom_original }))) return;
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
                        {{ $t('classes.show.back') }}
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
                    <span v-if="classe.description">{{ classe.description }}</span>
                </div>
            </div>

            <!-- Liste des étudiants -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        {{ $t('classes.show.students') }}
                        <span class="text-muted-foreground ml-2 text-sm font-normal">
                            ({{ etudiants.length }})
                        </span>
                    </CardTitle>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" @click="showImportDialog = true">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ $t('classes.show.import_csv') }}
                        </Button>
                        <Button size="sm" @click="openAdd">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('classes.show.add_student') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_da') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_first_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_email') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('classes.show.table_header_status') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('classes.show.table_header_actions') }}</th>
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
                                        {{ $t('classes.show.no_students') }}
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
                            {{ $t('classes.show.groups_title') }}
                            <span class="text-muted-foreground text-sm font-normal">
                                ({{ groupes.length }})
                            </span>
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="groupes.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                        {{ $t('classes.show.no_groups') }}
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="groupe in groupes"
                            :key="groupe.id"
                            class="border rounded-lg p-4 flex flex-col gap-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-sm">{{ $t('classes.groupes.group_number', { n: groupe.numero }) }}</p>
                                <Button size="sm" variant="outline" as-child>
                                    <Link :href="`/classes/${classe.id}/groupes/${groupe.id}`">
                                        {{ $t('classes.show.groups_see') }}
                                    </Link>
                                </Button>
                            </div>

                            <!-- Membres -->
                            <div>
                                <p class="text-muted-foreground text-xs font-medium mb-1">{{ $t('groupes.show.members') }}</p>
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
                                <p class="text-muted-foreground text-xs font-medium mb-1">{{ $t('groupes.show.thematic') }}</p>
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
                            {{ $t('classes.show.documents_title') }}
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
                            {{ $t('classes.show.add_document') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <p v-if="docForm.errors.document" class="text-destructive mb-3 text-sm">
                        {{ docForm.errors.document }}
                    </p>

                    <div v-if="documents.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                        {{ $t('classes.show.no_documents') }}
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
                    <DialogTitle>{{ $t('classes.show.modal_add_student') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="add-prenom">{{ $t('classes.show.modal_first_name') }}</Label>
                            <Input id="add-prenom" v-model="addForm.prenom" :placeholder="$t('classes.show.modal_first_name')" />
                            <InputError :message="addForm.errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="add-nom">{{ $t('classes.show.modal_name') }}</Label>
                            <Input id="add-nom" v-model="addForm.nom" :placeholder="$t('classes.show.modal_name')" />
                            <InputError :message="addForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-da">{{ $t('classes.show.modal_da_number') }}</Label>
                        <Input id="add-da" v-model="addForm.no_da" :placeholder="$t('classes.show.modal_da_number')" />
                        <InputError :message="addForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-statut">{{ $t('classes.show.modal_course_status') }}</Label>
                        <Input id="add-statut" v-model="addForm.statut_cours" :placeholder="$t('classes.show.modal_course_status')" />
                        <InputError :message="addForm.errors.statut_cours" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="add-email">
                            {{ $t('classes.show.modal_email') }}
                            <span class="text-muted-foreground text-xs font-normal">
                                {{ $t('classes.show.modal_email_note') }}
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
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button type="submit" :disabled="addForm.processing">{{ $t('classes.show.modal_add') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Modifier étudiant -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('classes.show.modal_edit_student') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>{{ $t('classes.show.modal_first_name') }}</Label>
                            <Input v-model="editForm.prenom" :placeholder="$t('classes.show.modal_first_name')" />
                            <InputError :message="editForm.errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ $t('classes.show.modal_name') }}</Label>
                            <Input v-model="editForm.nom" :placeholder="$t('classes.show.modal_name')" />
                            <InputError :message="editForm.errors.nom" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_email') }}</Label>
                        <Input v-model="editForm.email" type="email" />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_da_number') }}</Label>
                        <Input v-model="editForm.no_da" />
                        <InputError :message="editForm.errors.no_da" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('classes.show.modal_course_status') }}</Label>
                        <Input v-model="editForm.statut_cours" :placeholder="$t('classes.show.modal_course_status')" />
                        <InputError :message="editForm.errors.statut_cours" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditDialog = false">
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">{{ $t('classes.show.modal_save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal : Import CSV -->
        <Dialog v-model:open="showImportDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('classes.show.modal_import_csv') }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitImport">
                    <p class="text-muted-foreground text-sm">
                        {{ $t('classes.show.modal_csv_format') }} <code>;</code>) :
                    </p>
                    <code class="bg-muted block rounded p-3 text-xs">
                        {{ $t('classes.show.modal_csv_fields') }}
                    </code>
                    <div class="grid gap-2">
                        <Label for="csv-file">{{ $t('classes.show.modal_csv_file') }}</Label>
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
                            {{ $t('classes.show.modal_cancel') }}
                        </Button>
                        <Button type="submit" :disabled="importForm.processing || !importForm.csv">
                            {{ $t('classes.show.modal_import') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
