<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    people: Array,
});

function destroy(id) {
    if (confirm('¿Eliminar este responsable?')) {
        router.delete(route('responsible-people.destroy', id));
    }
}
</script>

<template>
    <Head title="Responsables" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">
                    Personas responsables
                </h2>
                <Link :href="route('responsible-people.create')">
                    <PrimaryButton>Nuevo responsable</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <ul
                    class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-sm"
                >
                    <li
                        v-for="p in people"
                        :key="p.id"
                        class="flex items-center justify-between px-4 py-4"
                    >
                        <div>
                            <p class="font-medium text-gray-900">{{ p.name }}</p>
                            <p
                                v-if="p.email"
                                class="text-sm text-gray-500"
                            >
                                {{ p.email }}
                            </p>
                        </div>
                        <div class="flex gap-3 text-sm">
                            <Link
                                :href="
                                    route('responsible-people.edit', p.id)
                                "
                                class="text-indigo-600 hover:underline"
                                >Editar</Link
                            >
                            <button
                                type="button"
                                class="text-red-600 hover:underline"
                                @click="destroy(p.id)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </li>
                    <li
                        v-if="!people?.length"
                        class="px-4 py-8 text-center text-gray-500"
                    >
                        No hay responsables. Créalos para dividir compras.
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
