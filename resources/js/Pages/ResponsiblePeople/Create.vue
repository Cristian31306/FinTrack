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
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Volver</Link
                >
                <h2 class="text-xl font-semibold text-gray-800">
                    Nuevo responsable
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
                <form
                    class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
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
                    <PrimaryButton :disabled="form.processing">
                        Guardar
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
