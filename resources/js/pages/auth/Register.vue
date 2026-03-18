<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        title="Inscription"
        description="L'inscription publique n'est pas disponible."
    >
        <Head title="Inscription" />

        <div class="flex flex-col gap-4 text-center text-sm text-muted-foreground">
            <p>
                Les comptes sont créés par les administrateurs ou les enseignants.
                Veuillez contacter votre établissement.
            </p>
            <TextLink :href="login()" class="underline underline-offset-4">
                Retour à la connexion
            </TextLink>
        </div>
    </AuthBase>
</template>
