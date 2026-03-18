<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres du profil',
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Paramètres du profil" />

        <h1 class="sr-only">Paramètres du profil</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Informations du profil"
                    description="Mettez à jour votre prénom, nom et adresse courriel"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="prenom">Prénom</Label>
                            <Input
                                id="prenom"
                                class="mt-1 block w-full"
                                name="prenom"
                                :default-value="user.prenom"
                                required
                                autocomplete="given-name"
                                placeholder="Prénom"
                            />
                            <InputError class="mt-2" :message="errors.prenom" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nom">Nom</Label>
                            <Input
                                id="nom"
                                class="mt-1 block w-full"
                                name="nom"
                                :default-value="user.nom"
                                required
                                autocomplete="family-name"
                                placeholder="Nom de famille"
                            />
                            <InputError class="mt-2" :message="errors.nom" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Adresse courriel</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Adresse courriel"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Votre adresse courriel n'est pas vérifiée.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Cliquez ici pour renvoyer le courriel de vérification.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Un nouveau lien de vérification a été envoyé.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">Enregistrer</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                                Enregistré.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
