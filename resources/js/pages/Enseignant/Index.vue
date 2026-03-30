<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BookOpen, ExternalLink, Pencil, Plus, Send, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Classe = {
    id: number;
    nom_cours: string;
    description: string | null;
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

type TravailRemis = {
    id: number;
    titre_projet: string | null;
    remis_le: string;
    groupe: {
        id: number;
        nom: string;
        classe_id: number;
    };
    membres: { id: number; prenom: string; nom: string }[];
};

type Props = {
    classes: Classe[];
    thematiques: Thematique[];
    travauxRemis: TravailRemis[];
};

const props = defineProps<Props>();
const { t } = useI18n();

// ─── Classes ──────────────────────────────────────────────────────────────────
const showCreateClasseDialog = ref(false);
const showEditClasseDialog = ref(false);
const editingClasseId = ref<number | null>(null);

const classeForm = useForm({
    nom_cours: '',
    description: '',
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
    classeForm.code = classe.code;
    classeForm.groupe = classe.groupe;
    showEditClasseDialog.value = true;
}

function submitEditClasse() {
    if (!editingClasseId.value) {
return;
}

    classeForm.put(`/classes/${editingClasseId.value}`, {
        onSuccess: () => {
            showEditClasseDialog.value = false;
        },
    });
}

const deleteClasseForm = useForm({});

function deleteClasse(classe: Classe) {
    if (!confirm(t('enseignant.index.confirm_delete_class', { nom: classe.nom_cours }))) {
return;
}

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
    if (!editingThematiqueId.value) {
return;
}

    thematiqueForm.put(`/thematiques/${editingThematiqueId.value}`, {
        onSuccess: () => {
            showEditThematiqueDialog.value = false;
        },
    });
}

const deleteThematiqueForm = useForm({});

function deleteThematique(thematique: Thematique) {
    if (!confirm(t('enseignant.index.confirm_delete_thematic', { nom: thematique.nom }))) {
return;
}

    deleteThematiqueForm.delete(`/thematiques/${thematique.id}`);
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('enseignant.index.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <Heading
                :title="$t('enseignant.index.heading_title')"
                :description="$t('enseignant.index.heading_description')"
            />

            <!-- ─── Mes classes ─────────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ $t('enseignant.index.my_classes') }}</CardTitle>
                    <Button size="sm" @click="openCreateClasse">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('enseignant.index.new_class') }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_code') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_group') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_course_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium text-center">{{ $t('enseignant.index.table_header_students') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('enseignant.index.table_header_actions') }}</th>
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
                                    <td colspan="5" class="text-muted-foreground py-6 text-center">
                                        {{ $t('enseignant.index.no_classes') }}
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
                            {{ $t('enseignant.index.my_thematic') }}
                        </div>
                    </CardTitle>
                    <Button size="sm" @click="openCreateThematique">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('enseignant.index.new_thematic') }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_thematic_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_historical_period') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_description') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('enseignant.index.table_header_actions') }}</th>
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
                                        {{ $t('enseignant.index.no_thematic') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <!-- ─── Travaux remis récemment ────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center gap-2">
                    <Send class="h-5 w-5" />
                    <CardTitle>{{ $t('enseignant.index.recent_submissions') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_group_name') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_project_title') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_members') }}</th>
                                    <th class="pb-3 pr-4 font-medium">{{ $t('enseignant.index.table_header_submitted_at') }}</th>
                                    <th class="pb-3 font-medium">{{ $t('enseignant.index.table_header_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="travail in travauxRemis"
                                    :key="travail.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">{{ travail.groupe.nom }}</td>
                                    <td class="text-muted-foreground py-3 pr-4">
                                        {{ travail.titre_projet ?? '—' }}
                                    </td>
                                    <td class="text-muted-foreground py-3 pr-4">
                                        {{ travail.membres.map(m => `${m.prenom} ${m.nom}`).join(', ') }}
                                    </td>
                                    <td class="py-3 pr-4 tabular-nums">
                                        {{ new Date(travail.remis_le).toLocaleDateString() }}
                                    </td>
                                    <td class="py-3">
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/classes/${travail.groupe.classe_id}/groupes/${travail.groupe.id}/projets/edit`">
                                                <ExternalLink class="h-4 w-4" />
                                                {{ $t('enseignant.index.view_project') }}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="travauxRemis.length === 0">
                                    <td colspan="5" class="text-muted-foreground py-6 text-center">
                                        {{ $t('enseignant.index.no_submissions') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal : Créer classe -->
        <FormDialog
            v-model:open="showCreateClasseDialog"
            :title="$t('enseignant.index.modal_create_class')"
            :is-loading="classeForm.processing"
            :submit-label="$t('common.add')"
            @submit="submitCreateClasse"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="code">{{ $t('enseignant.index.modal_course_code') }}</Label>
                    <Input id="code" v-model="classeForm.code" :placeholder="$t('enseignant.index.modal_course_code_placeholder')" />
                    <InputError :message="classeForm.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="groupe">{{ $t('enseignant.index.modal_group') }}</Label>
                    <Input id="groupe" v-model="classeForm.groupe" :placeholder="$t('enseignant.index.modal_group_placeholder')" />
                    <InputError :message="classeForm.errors.groupe" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="nom_cours">{{ $t('enseignant.index.modal_course_name') }}</Label>
                <Input id="nom_cours" v-model="classeForm.nom_cours" :placeholder="$t('enseignant.index.modal_course_name_placeholder')" />
                <InputError :message="classeForm.errors.nom_cours" />
            </div>
            <div class="grid gap-2">
                <Label for="description">{{ $t('enseignant.index.modal_description') }}</Label>
                <Input id="description" v-model="classeForm.description" :placeholder="$t('enseignant.index.modal_description_placeholder')" />
                <InputError :message="classeForm.errors.description" />
            </div>
        </FormDialog>

        <!-- Modal : Modifier classe -->
        <FormDialog
            v-model:open="showEditClasseDialog"
            :title="$t('enseignant.index.modal_edit_class')"
            :is-loading="classeForm.processing"
            @submit="submitEditClasse"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>{{ $t('enseignant.index.modal_course_code') }}</Label>
                    <Input v-model="classeForm.code" :placeholder="$t('enseignant.index.modal_course_code_placeholder')" />
                    <InputError :message="classeForm.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('enseignant.index.modal_group') }}</Label>
                    <Input v-model="classeForm.groupe" :placeholder="$t('enseignant.index.modal_group_placeholder')" />
                    <InputError :message="classeForm.errors.groupe" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_course_name') }}</Label>
                <Input v-model="classeForm.nom_cours" :placeholder="$t('enseignant.index.modal_course_name_placeholder')" />
                <InputError :message="classeForm.errors.nom_cours" />
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_description') }}</Label>
                <Input v-model="classeForm.description" :placeholder="$t('enseignant.index.modal_description_placeholder')" />
                <InputError :message="classeForm.errors.description" />
            </div>
        </FormDialog>

        <!-- Modal : Créer thématique -->
        <FormDialog
            v-model:open="showCreateThematiqueDialog"
            :title="$t('enseignant.index.modal_create_thematic')"
            :is-loading="thematiqueForm.processing"
            :submit-label="$t('common.add')"
            @submit="submitCreateThematique"
        >
            <div class="grid gap-2">
                <Label for="nom-theme">{{ $t('enseignant.index.modal_thematic_name') }}</Label>
                <Input id="nom-theme" v-model="thematiqueForm.nom" :placeholder="$t('enseignant.index.modal_thematic_name_placeholder')" />
                <InputError :message="thematiqueForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label for="periode">{{ $t('enseignant.index.modal_historical_period') }}</Label>
                <Input id="periode" v-model="thematiqueForm.periode_historique" :placeholder="$t('enseignant.index.modal_historical_period_placeholder')" />
                <InputError :message="thematiqueForm.errors.periode_historique" />
            </div>
            <div class="grid gap-2">
                <Label for="desc-theme">{{ $t('enseignant.index.modal_thematic_description') }}</Label>
                <Input id="desc-theme" v-model="thematiqueForm.description" :placeholder="$t('enseignant.index.modal_thematic_description_placeholder')" />
                <InputError :message="thematiqueForm.errors.description" />
            </div>
        </FormDialog>

        <!-- Modal : Modifier thématique -->
        <FormDialog
            v-model:open="showEditThematiqueDialog"
            :title="$t('enseignant.index.modal_edit_thematic')"
            :is-loading="thematiqueForm.processing"
            @submit="submitEditThematique"
        >
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_thematic_name') }}</Label>
                <Input v-model="thematiqueForm.nom" :placeholder="$t('enseignant.index.modal_thematic_name_placeholder')" />
                <InputError :message="thematiqueForm.errors.nom" />
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_historical_period') }}</Label>
                <Input v-model="thematiqueForm.periode_historique" :placeholder="$t('enseignant.index.modal_historical_period_placeholder')" />
                <InputError :message="thematiqueForm.errors.periode_historique" />
            </div>
            <div class="grid gap-2">
                <Label>{{ $t('enseignant.index.modal_thematic_description') }}</Label>
                <Input v-model="thematiqueForm.description" :placeholder="$t('enseignant.index.modal_thematic_description_placeholder')" />
                <InputError :message="thematiqueForm.errors.description" />
            </div>
        </FormDialog>
    </AppLayout>
</template>
