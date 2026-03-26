<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link, router } from '@inertiajs/vue3';
import * as LucideIcons from 'lucide-vue-next';

const props = defineProps({
    purchase: Object,
});

function money(n) {
    return Number(n).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function markPaid(pr) {
    router.patch(
        route('purchase-responsibles.paid', {
            purchase: props.purchase.id,
            purchase_responsible: pr.id,
        }),
    );
}

function markPending(pr) {
    router.patch(
        route('purchase-responsibles.pending', {
            purchase: props.purchase.id,
            purchase_responsible: pr.id,
        }),
    );
}
</script>

<template>
    <Head :title="purchase.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('purchases.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 transition-colors shadow-sm"
                >
                    <component :is="LucideIcons.ArrowLeft" class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="text-4xl font-black tracking-tighter uppercase text-[#111111] flex items-center gap-3">
                        {{ purchase.name }}
                    </h2>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">
                        Detalle de movimiento institucional • {{ formatDateDMY(purchase.purchase_date) }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                
                <!-- Action Bar -->
                <div class="mb-8 flex flex-col sm:flex-row justify-end gap-3">
                    <Link
                        :href="route('purchases.edit', purchase.id)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#C8B07D] px-6 py-3 text-[12px] font-black uppercase tracking-widest text-[#111111] shadow-xl shadow-[#C8B07D]/20 transition-all hover:scale-105 hover:bg-[#A68F5B]"
                    >
                        <component :is="LucideIcons.Settings2" class="h-5 w-5" />
                        Editar Detalles
                    </Link>
                </div>

                <!-- Bento Overview -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-8">
                    <!-- Total y Categoría -->
                    <div class="group relative overflow-hidden rounded-[2.5rem] border border-black/5 bg-white p-8 shadow-premium md:col-span-2 transition-all hover:shadow-xl">
                        <div class="absolute -right-4 -top-4 h-48 w-48 rounded-full opacity-5 transition-transform duration-700 group-hover:scale-150" :style="{ backgroundColor: purchase.category?.color || '#cbd5e1' }"></div>
                        <div class="flex flex-col sm:flex-row justify-between sm:items-end gap-6 h-full relative z-10">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 mb-4 border shadow-sm backdrop-blur-sm" :style="{ borderColor: purchase.category?.color || '#cbd5e1', backgroundColor: (purchase.category?.color || '#cbd5e1') + '15', color: purchase.category?.color || '#475569' }">
                                    <component :is="purchase.category?.icon ? LucideIcons[purchase.category.icon] : LucideIcons.Tag" class="h-4 w-4" />
                                    <span class="text-xs font-bold uppercase tracking-wider">{{ purchase.category?.name || 'Sin Categoría' }}</span>
                                </div>
                                <h3 class="font-outfit text-5xl sm:text-6xl font-black tracking-tight text-slate-900">
                                    {{ money(purchase.total_amount) }}
                                </h3>
                                <p class="mt-2 text-sm font-bold uppercase tracking-widest text-slate-400">Total de Compra</p>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Pagado con</p>
                                <div class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 border border-slate-200 shadow-sm">
                                    <component :is="LucideIcons.CreditCard" class="h-5 w-5 text-slate-600" />
                                    <span class="text-sm font-black tracking-wide text-slate-800">{{ formatCardLabel(purchase.credit_card) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cuotas Info -->
                    <div class="group relative overflow-hidden rounded-[2.5rem] border border-black/5 bg-gray-50 p-8 shadow-premium flex flex-col justify-center items-center text-center transition-all hover:shadow-xl hover:bg-white">
                        <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#C8B07D]/10 text-[#C8B07D] shadow-sm transition-transform group-hover:scale-110 group-hover:-rotate-3">
                            <component :is="LucideIcons.CalendarDays" class="h-8 w-8" />
                        </div>
                        <h4 class="text-5xl font-black text-[#111111]">{{ purchase.installments_count }}</h4>
                        <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Cuotas Mensuales</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    
                    <!-- Installments Table -->
                    <div class="rounded-[2.5rem] border border-white bg-white/70 p-8 shadow-2xl backdrop-blur-2xl">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="font-outfit text-2xl font-black text-slate-900">Plan de Amortización</h3>
                            <div class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase text-slate-500">
                                {{ purchase.installments.length }} Registros
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-400">
                                        <th class="py-3 pr-4">#</th>
                                        <th class="py-3 px-4">Interés</th>
                                        <th class="py-3 px-4">Total Cuota</th>
                                        <th class="py-3 pl-4">Corte Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="ins in purchase.installments" :key="ins.id" class="transition-colors hover:bg-slate-50/50 group">
                                        <td class="py-4 pr-4">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-black text-slate-600 transition-colors group-hover:bg-brand-50 group-hover:text-brand-600">{{ ins.installment_number }}</span>
                                        </td>
                                        <td class="py-4 px-4 font-medium text-amber-600">
                                            {{ money(ins.interest_amount) }}
                                        </td>
                                        <td class="py-4 px-4 font-outfit font-black text-slate-900 text-[15px]">
                                            {{ money(ins.total_amount) }}
                                        </td>
                                        <td class="py-4 pl-4 text-xs font-medium text-slate-500">
                                            <Link v-if="ins.cut_id" :href="route('cuts.show', ins.cut_id)" class="inline-flex items-center gap-1.5 rounded-lg bg-[#C8B07D]/10 px-2.5 py-1 text-[#C8B07D] hover:bg-[#C8B07D]/20 transition-colors">
                                                Cierre {{ formatDateDMY(ins.statement_close_date) }}
                                                <component :is="LucideIcons.ArrowUpRight" class="h-3 w-3" />
                                            </Link>
                                            <span v-else class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1 text-gray-400">
                                                Cierre {{ formatDateDMY(ins.statement_close_date) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Responsibles -->
                    <div v-if="purchase.purchase_responsibles?.length" class="flex flex-col gap-6">
                        <div class="rounded-[2.5rem] border border-white bg-slate-50/50 p-8 shadow-inner ring-1 ring-slate-100">
                            <h3 class="mb-6 font-outfit text-2xl font-black text-slate-900">Cuentas Claras</h3>
                            <div class="space-y-4">
                                <div v-for="pr in purchase.purchase_responsibles" :key="pr.id" class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100 transition-shadow hover:shadow-md">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl font-black text-slate-400" :class="pr.status === 'pagado' ? 'bg-emerald-50 text-emerald-600 ring-4 ring-emerald-50/50' : ''">
                                            {{ pr.responsible_person?.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900">{{ pr.responsible_person?.name }}</p>
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-0.5">
                                                Participación: {{ pr.split_type === 'porcentaje' ? pr.split_value + '%' : 'Fijo' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-outfit text-xl font-black transition-all" :class="pr.status === 'pagado' ? 'text-emerald-600 line-through opacity-70' : 'text-slate-900'">
                                            {{ money(pr.owed_amount) }}
                                        </p>
                                        <button v-if="pr.status !== 'pagado'" @click="markPaid(pr)" class="mt-1 text-[10px] font-black uppercase tracking-widest text-[#C8B07D] hover:text-[#A68F5B] hover:underline transition-all">
                                            Marcar Pagado
                                        </button>
                                        <button v-else @click="markPending(pr)" class="mt-1 text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700 hover:underline transition-all">
                                            Deshacer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- End Responsibles -->

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
