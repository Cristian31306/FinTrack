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
                    <h2 class="font-outfit text-3xl font-extrabold tracking-tight text-slate-900">
                        Cortes de Facturación
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">Administra y revisa los periodos de cobro de tus tarjetas.</p>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-[2rem] border border-white bg-white/60 shadow-premium backdrop-blur-xl transition-all">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-slate-50/50 text-xs font-bold uppercase tracking-wider text-slate-400">
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
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 font-bold">
                                                {{ c.credit_card?.bank_name?.charAt(0) || 'CC' }}
                                            </div>
                                            <span class="font-bold text-slate-900">{{ formatCardLabel(c.credit_card) }}</span>
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
                                        <span class="font-outfit font-bold" :class="c.remaining_balance > 0 ? 'text-brand-600' : 'text-emerald-600'">
                                            {{ money(c.remaining_balance) }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <span :class="[
                                            'inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider',
                                            c.status === 'pagado' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'
                                        ]">
                                            {{ c.status }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <Link
                                            :href="route('cuts.show', c.id)"
                                            class="inline-flex items-center gap-1 font-bold text-brand-600 transition-all hover:gap-2 hover:text-brand-700"
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
                                    'rounded-xl px-4 py-2 text-sm font-bold transition-all',
                                    l.active
                                        ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30'
                                        : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50',
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
