<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="font-outfit text-2xl font-black tracking-tight text-red-600">
                Zona de Peligro
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Si decides eliminar tu cuenta, todos tus registros financieros y configuraciones se borrarán permanentemente. Esta acción es irreversible.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Eliminar mi Cuenta</DangerButton>

        <Modal
            :show="confirmingUserDeletion"
            @close="closeModal"
            max-width="md"
        >
            <div class="p-8 bg-white/90 backdrop-blur-xl rounded-[2.5rem]">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h2 class="font-outfit text-2xl font-black text-slate-900 tracking-tight">
                    ¿Confirmas que deseas irte?
                </h2>

                <p class="mt-3 text-sm font-medium text-slate-500 leading-relaxed">
                    Para confirmar la eliminación permanente de tu cuenta, por favor ingresa tu contraseña a continuación.
                </p>

                <div class="mt-8">
                    <InputLabel
                        for="password"
                        value="Tu Contraseña"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full"
                        placeholder="Ingresa tu contraseña para confirmar"
                        @keyup.enter="deleteUser"
                    />

                    <InputError
                        :message="form.errors.password"
                        class="mt-2"
                    />
                </div>

                <div class="mt-10 flex flex-col sm:flex-row gap-3">
                    <button
                        @click="closeModal"
                        class="flex-1 rounded-xl bg-slate-100 px-6 py-3 text-sm font-bold text-slate-600 transition-all hover:bg-slate-200"
                    >
                        No, quiero quedarme
                    </button>

                    <DangerButton
                        class="flex-1 justify-center rounded-xl py-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Sí, eliminar cuenta
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
