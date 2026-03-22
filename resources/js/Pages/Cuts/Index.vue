<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    cuts: Object,
});

function money(n) {
    return Number(n).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <Head title="Cortes" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                Cortes de facturación
            </h2>
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
                                    Tarjeta
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-600"
                                >
                                    Periodo
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                >
                                    Total corte
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                >
                                    Saldo
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-gray-600"
                                >
                                    Estado
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-600"
                                />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="c in cuts.data"
                                :key="c.id"
                            >
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ formatCardLabel(c.credit_card) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ formatDateDMY(c.period_start) }} →
                                    {{ formatDateDMY(c.period_end) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(c.total_accrued) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium">
                                    {{ money(c.remaining_balance) }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ c.status }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('cuts.show', c.id)"
                                        class="text-indigo-600 hover:underline"
                                        >Detalle</Link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-if="cuts.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t p-4"
                    >
                        <Link
                            v-for="l in cuts.links"
                            :key="l.label"
                            :href="l.url || '#'"
                            :class="[
                                'rounded px-3 py-1 text-sm',
                                l.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-700',
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
