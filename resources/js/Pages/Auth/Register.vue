<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const countries = [
    { name: 'Colombia', code: '+57', flag: '🇨🇴' },
    { name: 'Venezuela', code: '+58', flag: '🇻🇪' },
    { name: 'Ecuador', code: '+593', flag: '🇪🇨' },
    { name: 'Perú', code: '+51', flag: '🇵🇪' },
    { name: 'Chile', code: '+56', flag: '🇨🇱' },
    { name: 'Argentina', code: '+54', flag: '🇦🇷' },
    { name: 'México', code: '+52', flag: '🇲🇽' },
    { name: 'Estados Unidos', code: '+1', flag: '🇺🇸' },
    { name: 'España', code: '+34', flag: '🇪🇸' },
];

const selectedCountry = ref(countries[0]);
const localPhone = ref('');

const form = useForm({
    name: '',
    email: '',
    phone_number: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    // Combinar código de país con el número local
    form.phone_number = selectedCountry.value.code + localPhone.value.replace(/\s+/g, '');
    
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registro" />

        <h1 class="mb-2 text-center text-3xl font-black uppercase tracking-tighter text-[#111111]">
            Crear Cuenta
        </h1>
        <p class="mb-8 text-center text-xs font-black uppercase tracking-[0.2em] text-gray-500">
            Únete a la elite de FinTrack
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
                    value="WhatsApp"
                />

                <div class="mt-1 flex gap-2">
                    <div class="relative w-32 shrink-0">
                        <select
                            v-model="selectedCountry"
                            class="block w-full rounded-md border-slate-300 py-2 pl-3 pr-10 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option
                                v-for="c in countries"
                                :key="c.code"
                                :value="c"
                            >
                                {{ c.flag }} {{ c.code }}
                            </option>
                        </select>
                    </div>

                    <TextInput
                        id="phone_number"
                        v-model="localPhone"
                        type="text"
                        class="block w-full border-slate-300"
                        placeholder="Número de celular"
                        required
                    />
                </div>

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
                    class="text-center text-[10px] font-black uppercase tracking-widest text-[#C8B07D] hover:text-[#A68F5B] transition-colors sm:me-auto sm:text-left"
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
