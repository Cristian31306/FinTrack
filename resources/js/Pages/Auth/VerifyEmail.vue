<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: String },
    email: { type: String },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verificar correo" />

        <h1 class="mb-1 text-center text-xl font-bold text-slate-900">
            Verifica tu correo
        </h1>
        <p class="mb-4 text-center text-sm leading-relaxed text-slate-600">
            Gracias por registrarte. Antes de continuar, revisa tu bandeja y haz
            clic en el enlace que te enviamos. Si no lo ves, puedes pedir otro
            correo.
        </p>

        <div
            v-if="verificationLinkSent"
            class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"
        >
            Te enviamos un nuevo enlace al correo <b>{{ email }}</b> que usaste al registrarte.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Reenviar correo de verificación
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-center text-sm text-slate-600 underline decoration-slate-400 underline-offset-2 hover:text-emerald-700"
                >
                    Cerrar sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
