<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="font-outfit text-2xl font-black tracking-tight text-slate-900">
                Seguridad de la Cuenta
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Asegúrate de usar una contraseña robusta para proteger tus datos financieros.
            </p>
        </header>

        <form
            class="mt-8 space-y-6"
            @submit.prevent="updatePassword"
        >
            <div class="grid gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <InputLabel
                        for="current_password"
                        value="Contraseña Actual"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />

                    <TextInput
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="block w-full"
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />

                    <InputError
                        :message="form.errors.current_password"
                        class="mt-2"
                    />
                </div>

                <div>
                    <InputLabel
                        for="password"
                        value="Nueva Contraseña"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full"
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />

                    <InputError
                        :message="form.errors.password"
                        class="mt-2"
                    />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        value="Confirmar Contraseña"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />

                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="block w-full"
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />

                    <InputError
                        :message="form.errors.password_confirmation"
                        class="mt-2"
                    />
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4">
                <PrimaryButton :disabled="form.processing">Actualizar Seguridad</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm font-bold text-emerald-600"
                    >
                        ✓ Actualizado con éxito.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
