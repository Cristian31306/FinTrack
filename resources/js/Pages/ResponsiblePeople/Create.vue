<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
});
</script>

<template>
    <Head title="Nuevo responsable" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('responsible-people.index')"
                    class="group flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm transition-all hover:bg-slate-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h2 class="text-3xl font-black tracking-tighter uppercase text-[#111111]">
                        Nuevo Responsable
                    </h2>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Vinculación de aliado externo</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-[2.5rem] bg-white p-8 shadow-premium lg:p-10"
                    @submit.prevent="form.post(route('responsible-people.store'))"
                >
                    <div>
                        <InputLabel
                            for="name"
                            value="Nombre"
                        />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.name"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="email"
                            value="Email (opcional)"
                        />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <PrimaryButton class="w-full justify-center py-4" :disabled="form.processing">
                        Guardar Responsable
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
