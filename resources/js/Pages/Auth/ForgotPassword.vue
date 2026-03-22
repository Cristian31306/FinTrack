<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar contraseña" />

        <h1 class="mb-1 text-center text-xl font-bold text-slate-900">
            ¿Olvidaste tu contraseña?
        </h1>
        <p class="mb-4 text-center text-sm leading-relaxed text-slate-600">
            Indica tu correo y te enviaremos un enlace para elegir una nueva
            contraseña.
        </p>

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel
                    for="email"
                    value="Correo electrónico"
                />

                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full border-slate-300"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.email"
                />
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <Link
                    :href="route('login')"
                    class="text-center text-sm text-slate-600 underline decoration-slate-400 underline-offset-2 hover:text-emerald-700 sm:me-auto"
                >
                    Volver al inicio de sesión
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Enviar enlace
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
