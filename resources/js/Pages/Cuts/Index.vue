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
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-black tracking-tighter uppercase text-[#111111]">
                        Cortes de Facturación
                    </h2>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Control de periodos institucionales</p>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-[2rem] border border-white bg-white/60 shadow-premium backdrop-blur-xl transition-all">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-gray-50/50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    <th class="px-8 py-5">Tarjeta</th>
                                    <th class="px-8 py-5">Periodo</th>
                                    <th class="px-8 py-5 text-right">Total Corte</th>
                                    <th class="px-8 py-5 text-right">Saldo Pendiente</th>
                                    <th class="px-8 py-5 text-center">Estado</th>
                                    <th class="px-8 py-5 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="c in cuts.data" :key="c.id" class="group transition-colors hover:bg-white/60">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#C8B07D]/10 text-[#C8B07D] font-black">
                                                {{ c.credit_card?.bank_name?.charAt(0) || 'CC' }}
                                            </div>
                                            <span class="font-black text-[#111111]">{{ formatCardLabel(c.credit_card) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="text-sm text-slate-600">
                                            {{ formatDateDMY(c.period_start) }}
                                            <span class="mx-1 text-slate-300">→</span>
                                            {{ formatDateDMY(c.period_end) }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right font-outfit font-medium text-slate-600">
                                        {{ money(c.total_accrued) }}
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <span class="font-black" :class="c.remaining_balance > 0 ? 'text-[#C8B07D]' : 'text-emerald-600'">
                                            {{ money(c.remaining_balance) }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <span :class="[
                                            'inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest',
                                            c.status === 'pagado' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'
                                        ]">
                                            {{ c.status }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <Link
                                            :href="route('cuts.show', c.id)"
                                            class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-[#C8B07D] transition-all hover:gap-2 hover:text-[#A68F5B]"
                                        >
                                            Ver detalle
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-if="cuts.links?.length > 3" class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-8 py-6">
                        <p class="text-sm text-slate-500">Mostrando registros</p>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="l in cuts.links"
                                :key="l.label"
                                :href="l.url || '#'"
                                :class="[
                                    'rounded-xl px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-all',
                                    l.active
                                        ? 'bg-[#C8B07D] text-[#111111] shadow-lg shadow-[#C8B07D]/30'
                                        : 'bg-white text-gray-400 border border-gray-100 hover:bg-gray-50',
                                    !l.url ? 'pointer-events-none opacity-50' : '',
                                ]"
                                v-html="l.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
