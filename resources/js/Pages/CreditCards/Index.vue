<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    creditCards: Array,
});

function destroy(id) {
    if (confirm('¿Eliminar esta tarjeta? Se borrarán compras y cortes asociados.')) {
        router.delete(route('credit-cards.destroy', id));
    }
}
</script>

<template>
    <Head title="Tarjetas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Tarjetas</h2>
                <Link :href="route('credit-cards.create')">
                    <PrimaryButton>Nueva tarjeta</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                >
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-600"
                                >
                                    Nombre
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-600"
                                >
                                    Franquicia
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                >
                                    Cupo
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-gray-600"
                                >
                                    Corte / Pago
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="c in creditCards"
                                :key="c.id"
                            >
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ formatCardLabel(c) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ c.franchise }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-800">
                                    {{
                                        Number(c.credit_limit).toLocaleString(
                                            'es-ES',
                                            {
                                                minimumFractionDigits: 2,
                                            },
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ c.statement_day }} /
                                    {{ c.payment_day }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="
                                            route('credit-cards.edit', c.id)
                                        "
                                        class="text-indigo-600 hover:underline"
                                        >Editar</Link
                                    >
                                    <button
                                        type="button"
                                        class="ms-3 text-red-600 hover:underline"
                                        @click="destroy(c.id)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!creditCards?.length"
                        class="p-6 text-center text-gray-500"
                    >
                        No hay tarjetas registradas.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
