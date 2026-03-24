<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    phone_number: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registro" />

        <h1 class="mb-1 text-center text-xl font-bold text-slate-900">
            Crear cuenta
        </h1>
        <p class="mb-6 text-center text-sm text-slate-600">
            Empieza a organizar tus tarjetas con FinTrack
        </p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel
                    for="name"
                    value="Nombre"
                />

                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full border-slate-300"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.name"
                />
            </div>

            <div class="mt-4">
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
                    autocomplete="username"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.email"
                />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="phone_number"
                    value="WhatsApp (Formato internacional: +57...)"
                />

                <TextInput
                    id="phone_number"
                    v-model="form.phone_number"
                    type="text"
                    class="mt-1 block w-full border-slate-300"
                    placeholder="+57300..."
                    required
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.phone_number"
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
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password"
                />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar contraseña"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full border-slate-300"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div
                class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end"
            >
                <Link
                    :href="route('login')"
                    class="text-center text-sm text-slate-600 underline decoration-slate-400 underline-offset-2 hover:text-emerald-700 sm:me-auto sm:text-left"
                >
                    ¿Ya tienes cuenta? Ingresar
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Registrarme
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
