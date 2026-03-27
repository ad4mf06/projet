<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';
import { BookOpen, CheckCircle2, ChevronRight, FileEdit, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type ConclusionResume = {
    etudiant: Etudiant;
    a_redige: boolean;
};

type ProjetResume = {
    id: number;
    titre_projet: string | null;
    completion: number;
} | null;

type Groupe = {
    id: number;
    nom: string;
    classe_id: number;
};

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    projet: ProjetResume;
    conclusions: ConclusionResume[];
    estEnseignant: boolean;
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);
const { t } = useI18n();

function completionColor(pct: number): string {
    if (pct >= 80) return 'text-green-600 dark:text-green-400';
    if (pct >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-muted-foreground';
}

const projetUrl = computed(
    () => `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets/edit`,
);
</script>

<template>
    <AppLayout>
        <Head :title="t('projets.index.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/classes/${groupe.classe_id}/groupes/${groupe.id}`">
                        {{ t('projets.index.back_to_group') }}
                    </Link>
                </Button>
            </div>

            <Heading
                :title="t('projets.index.heading_title')"
                :description="`${groupe.nom} · ${classe.code} — ${classe.nom_cours}`"
            />

            <!-- Carte du projet partagé -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">
                        {{ projet?.titre_projet ?? t('projets.index.no_title') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <!-- Barre de progression du contenu partagé -->
                    <div v-if="projet">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">{{ t('projets.index.shared_content') }}</span>
                            <span
                                class="text-xs font-medium"
                                :class="completionColor(projet.completion)"
                            >
                                {{ projet.completion }}%
                            </span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="projet.completion >= 80 ? 'bg-green-500' : projet.completion >= 40 ? 'bg-yellow-500' : 'bg-primary/40'"
                                :style="{ width: `${projet.completion}%` }"
                            />
                        </div>
                    </div>

                    <!-- Conclusions par membre -->
                    <div>
                        <p class="text-xs text-muted-foreground mb-2 font-medium uppercase tracking-wide">
                            {{ t('projets.index.individual_conclusions') }}
                        </p>
                        <div class="space-y-1">
                            <div
                                v-for="item in conclusions"
                                :key="item.etudiant.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <CheckCircle2
                                    v-if="item.a_redige"
                                    class="h-4 w-4 shrink-0 text-green-500"
                                />
                                <XCircle
                                    v-else
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                                <span :class="item.a_redige ? '' : 'text-muted-foreground'">
                                    {{ item.etudiant.prenom }} {{ item.etudiant.nom }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton d'accès -->
                    <Button
                        size="sm"
                        :variant="!estEnseignant ? 'default' : 'outline'"
                        class="w-full sm:w-auto"
                        as-child
                    >
                        <Link :href="projetUrl">
                            <component
                                :is="!estEnseignant ? FileEdit : BookOpen"
                                class="mr-2 h-4 w-4"
                            />
                            {{ !estEnseignant ? t('projets.index.edit_project') : t('projets.index.view_project') }}
                            <ChevronRight class="ml-auto h-4 w-4" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
