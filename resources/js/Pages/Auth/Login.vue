<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Ingresar" />

        <h1 class="mb-1 text-center text-xl font-bold text-slate-900">
            Bienvenido de nuevo
        </h1>
        <p class="mb-6 text-center text-sm text-slate-600">
            Ingresa para ver tu panel y tus tarjetas
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

            <div class="mt-4">
                <InputLabel
                    for="password"
                    value="Contraseña"
                />

                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full border-slate-300"
                    required
                    autocomplete="current-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password"
                />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox
                        name="remember"
                        v-model:checked="form.remember"
                    />
                    <span class="ms-2 text-sm text-slate-600"
                        >Recordarme en este equipo</span
                    >
                </label>
            </div>

            <div
                class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="order-2 text-sm text-slate-600 underline decoration-slate-400 underline-offset-2 hover:text-emerald-700 sm:order-1"
                >
                    ¿Olvidaste tu contraseña?
                </Link>

                <PrimaryButton
                    class="order-1 sm:order-2"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Ingresar
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            ¿No tienes cuenta?
            <Link
                :href="route('register')"
                class="font-semibold text-emerald-700 hover:text-emerald-800"
            >
                Regístrate
            </Link>
        </p>
    </GuestLayout>
</template>
