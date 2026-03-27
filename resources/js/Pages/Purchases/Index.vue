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
                <div>
                    <h2 class="text-3xl font-black tracking-tighter uppercase text-[#111111]">Compras</h2>
                    <p class="mt-1 text-xs font-black uppercase tracking-[0.2em] text-gray-500">Historial de movimientos institucionales</p>
                </div>
                <Link :href="route('purchases.create')">
                    <PrimaryButton class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Registrar compra
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-[2rem] border border-black/5 bg-white shadow-premium"
                >
                    <!-- Desktop Table -->
                    <table class="hidden md:table min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th
                                    class="px-4 py-4 text-left text-[10px] font-black uppercase tracking-widest text-gray-400"
                                >
                                    Fecha
                                </th>
                                <th
                                    class="px-4 py-4 text-left text-[10px] font-black uppercase tracking-widest text-gray-400"
                                >
                                    Descripción
                                </th>
                                <th
                                    class="px-4 py-4 text-left text-[10px] font-black uppercase tracking-widest text-gray-400"
                                >
                                    Tarjeta
                                </th>
                                <th
                                    class="px-4 py-4 text-right text-[10px] font-black uppercase tracking-widest text-gray-400"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-4 py-4 text-center text-[10px] font-black uppercase tracking-widest text-gray-400"
                                >
                                    Cuotas
                                </th>
                                <th
                                    class="px-4 py-4 text-right text-[10px] font-black uppercase tracking-widest text-gray-400"
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
                                <td class="px-4 py-4 text-right">
                                    <Link
                                        :href="route('purchases.show', p.id)"
                                        class="text-[10px] font-black uppercase tracking-widest text-[#C8B07D] hover:underline"
                                        >Ver</Link
                                    >
                                    <button
                                        type="button"
                                        class="ms-3 text-[10px] font-black uppercase tracking-widest text-red-500 hover:underline"
                                        @click="destroy(p.id)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile Card View -->
                    <div class="md:hidden divide-y divide-gray-100">
                        <div
                            v-for="p in purchases.data"
                            :key="p.id"
                            class="p-4 space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    {{ formatDateDMY(p.purchase_date) }}
                                </span>
                                <div class="flex items-center gap-3">
                                    <Link
                                        :href="route('purchases.show', p.id)"
                                        class="text-[10px] font-black uppercase tracking-widest text-[#C8B07D] underline"
                                    >
                                        Ver
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-[10px] font-black uppercase tracking-widest text-red-500 underline"
                                        @click="destroy(p.id)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div 
                                    v-if="p.category"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl shadow-sm"
                                    :style="{ backgroundColor: p.category.color + '20', color: p.category.color }"
                                >
                                    <component :is="LucideIcons[p.category.icon] || LucideIcons.Tag" class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-bold text-gray-900 truncate">{{ p.name }}</h4>
                                    <p class="text-xs text-gray-500">{{ formatCardLabel(p.credit_card) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-black text-gray-900">{{ money(p.total_amount) }}</p>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">
                                        {{ p.installments_count }} {{ p.installments_count === 1 ? 'Cuota' : 'Cuotas' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                'rounded-lg px-3 py-1 text-[10px] font-black uppercase tracking-widest transition-all',
                                l.active
                                    ? 'bg-[#C8B07D] text-[#111111]'
                                    : 'bg-gray-50 text-gray-400 hover:bg-gray-100',
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
