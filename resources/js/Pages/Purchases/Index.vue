<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link, router } from '@inertiajs/vue3';
import * as LucideIcons from 'lucide-vue-next';

defineProps({
    purchases: Object,
});

function money(n) {
    return Number(n).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function destroy(id) {
    if (confirm('¿Eliminar esta compra y sus cuotas?')) {
        router.delete(route('purchases.destroy', id));
    }
}
</script>

<template>
    <Head title="Compras" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Compras</h2>
                <Link :href="route('purchases.create')">
                    <PrimaryButton>Registrar compra</PrimaryButton>
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
                                    Fecha
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-600"
                                >
                                    Descripción
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-600"
                                >
                                    Tarjeta
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-gray-600"
                                >
                                    Cuotas
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="p in purchases.data"
                                :key="p.id"
                            >
                                <td class="px-4 py-3 text-gray-600">
                                    {{ formatDateDMY(p.purchase_date) }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <div class="flex items-center gap-2">
                                        <div 
                                            v-if="p.category"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg shadow-sm"
                                            :style="{ backgroundColor: p.category.color + '20', color: p.category.color }"
                                            :title="p.category.name"
                                        >
                                            <component :is="LucideIcons[p.category.icon] || LucideIcons.Tag" class="h-4 w-4" />
                                        </div>
                                        <span>{{ p.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ formatCardLabel(p.credit_card) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(p.total_amount) }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ p.installments_count }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('purchases.show', p.id)"
                                        class="text-indigo-600 hover:underline"
                                        >Ver</Link
                                    >
                                    <button
                                        type="button"
                                        class="ms-3 text-red-600 hover:underline"
                                        @click="destroy(p.id)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!purchases.data?.length"
                        class="p-6 text-center text-gray-500"
                    >
                        No hay compras.
                    </p>
                    <div
                        v-if="purchases.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 p-4"
                    >
                        <Link
                            v-for="l in purchases.links"
                            :key="l.label"
                            :href="l.url || '#'"
                            :class="[
                                'rounded px-3 py-1 text-sm',
                                l.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                !l.url ? 'pointer-events-none opacity-50' : '',
                            ]"
                            v-html="l.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
