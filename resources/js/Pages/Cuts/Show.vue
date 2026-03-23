<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    cut: Object,
    paid_total: Number,
    remaining: Number,
    suggested_minimum: Number,
    minimum_percent: Number,
    payment_types: Array,
});

const payForm = useForm({
    credit_card_id: props.cut.credit_card_id,
    cut_id: props.cut.id,
    amount: String(props.suggested_minimum ?? ''),
    type: 'minimo',
    payment_date: new Date().toISOString().slice(0, 10),
});

function money(n) {
    return Number(n).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function paymentTypeLabel(t) {
    const map = {
        minimo: 'Pago mínimo',
        total: 'Pago total del corte',
        abono: 'Abono',
    };
    return map[t] ?? t;
}

function applyAmountForType(type) {
    if (type === 'minimo' || type === 'total') {
        payForm.amount =
            props.remaining <= 0.01 ? '' : String(props.remaining);
    } else {
        payForm.amount = '';
    }
}

watch(
    () => payForm.type,
    (t) => {
        applyAmountForType(t);
    },
);

watch(
    () => [props.remaining, props.suggested_minimum],
    () => {
        applyAmountForType(payForm.type);
    },
);

const paymentHint = computed(() => {
    const r = props.remaining;
    if (r <= 0.01) {
        return 'Este corte no tiene saldo pendiente.';
    }
    return `Se aplicará el pago por el valor total de lo facturado en este corte: ${money(r)}.`;
});

const abonoPreview = computed(() => {
    if (payForm.type !== 'abono' || props.remaining <= 0.01) {
        return '';
    }
    const raw = parseFloat(String(payForm.amount).replace(',', '.'));
    if (Number.isNaN(raw) || raw <= 0) {
        return '';
    }
    const left = Math.max(0, round2(props.remaining - raw));
    return `Abonarás ${money(raw)}; quedarían aprox. ${money(left)} pendientes en este corte.`;
});

function round2(x) {
    return Math.round(x * 100) / 100;
}

function submitPayment() {
    payForm.post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            payForm.reset('amount');
            payForm.type = 'abono';
            applyAmountForType('abono');
        },
    });
}
</script>

<template>
    <Head title="Detalle del Corte" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <nav class="mb-4 flex" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <Link :href="route('cuts.index')" class="text-sm font-medium text-slate-500 transition-colors hover:text-brand-600">
                                    Cortes
                                </Link>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="h-4 w-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="ms-1 text-sm font-medium text-slate-400 md:ms-2">Detalle</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <h2 class="font-outfit text-3xl font-extrabold tracking-tight text-slate-900">
                        {{ formatCardLabel(cut.credit_card) }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Periodo del <span class="font-semibold text-slate-700">{{ formatDateDMY(cut.period_start) }}</span> al <span class="font-semibold text-slate-700">{{ formatDateDMY(cut.period_end) }}</span>
                    </p>
                </div>
                <div class="mt-4 flex items-center gap-3 sm:mt-0">
                    <div :class="[
                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider shadow-sm',
                        cut.status === 'pagado' ? 'bg-emerald-100 text-emerald-700' : 'bg-brand-100 text-brand-700'
                    ]">
                        {{ cut.status }}
                    </div>
                </div>
            </div>
        </template>

        <div class="pb-12 pt-4">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Bento Stats Grid -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Total Facturado -->
                    <div class="group relative overflow-hidden rounded-[2rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:shadow-premium-hover">
                        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-slate-50 opacity-50 transition-transform group-hover:scale-110"></div>
                        <div class="relative">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 shadow-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-slate-500">TOTAL FACTURADO</p>
                            <h3 class="mt-1 font-outfit text-3xl font-bold tracking-tight text-slate-900">
                                {{ money(cut.total_accrued) }}
                            </h3>
                        </div>
                    </div>

                    <!-- Pagado -->
                    <div class="group relative overflow-hidden rounded-[2rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:shadow-premium-hover">
                        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-50 opacity-50 transition-transform group-hover:scale-110"></div>
                        <div class="relative">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-slate-500">PAGADO HASTA AHORA</p>
                            <h3 class="mt-1 font-outfit text-3xl font-bold tracking-tight text-emerald-600">
                                {{ money(paid_total) }}
                            </h3>
                            <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div 
                                    class="h-full bg-emerald-500 transition-all duration-1000" 
                                    :style="{ width: `${Math.min(100, (paid_total / cut.total_accrued) * 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pendiente -->
                    <div class="group relative overflow-hidden rounded-[2rem] border border-brand-100 bg-gradient-to-br from-brand-600 to-brand-500 p-8 shadow-2xl transition-all hover:-translate-y-1">
                        <div class="absolute -bottom-4 -right-4 h-32 w-32 rounded-full bg-white/10 opacity-20"></div>
                        <div class="relative">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 text-white backdrop-blur-md">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-white/80">PENDIENTE POR PAGAR</p>
                            <h3 class="mt-1 font-outfit text-3xl font-bold tracking-tight text-white">
                                {{ money(remaining) }}
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="mt-12 grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: Installments & Payments -->
                    <div class="space-y-8 lg:col-span-2">
                        <!-- Installments List -->
                        <div class="overflow-hidden rounded-3xl border border-white bg-white/40 shadow-premium backdrop-blur-sm">
                            <div class="border-b border-slate-100 bg-white/60 px-8 py-6">
                                <h3 class="font-outfit text-lg font-bold text-slate-900">Consumos Incluidos</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="bg-slate-50/50 text-xs font-bold uppercase tracking-wider text-slate-400">
                                            <th class="px-8 py-4">Compra / Descripción</th>
                                            <th class="px-4 py-4">Cuota</th>
                                            <th class="px-8 py-4 text-right">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="ins in cut.installments" :key="ins.id" class="group transition-colors hover:bg-white/60">
                                            <td class="px-8 py-5">
                                                <Link
                                                    v-if="ins.purchase_id"
                                                    :href="route('purchases.show', ins.purchase_id)"
                                                    class="font-semibold text-slate-700 transition-colors hover:text-brand-600"
                                                >
                                                    {{ ins.purchase?.name }}
                                                </Link>
                                                <p class="text-xs text-slate-400">ID #{{ ins.id }}</p>
                                            </td>
                                            <td class="px-4 py-5">
                                                <span class="inline-flex rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">
                                                    #{{ ins.installment_number }}
                                                </span>
                                            </td>
                                            <td class="px-8 py-5 text-right font-outfit font-bold text-slate-900">
                                                {{ money(ins.total_amount) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Registered Payments -->
                        <div v-if="cut.payments?.length" class="overflow-hidden rounded-3xl border border-white bg-white/40 shadow-premium backdrop-blur-sm">
                            <div class="border-b border-slate-100 bg-white/60 px-8 py-6">
                                <h3 class="font-outfit text-lg font-bold text-slate-900">Historial de Pagos</h3>
                            </div>
                            <div class="p-8">
                                <div class="space-y-4">
                                    <div v-for="p in cut.payments" :key="p.id" class="flex items-center justify-between rounded-2xl bg-white/60 p-4 shadow-sm transition-transform hover:scale-[1.01]">
                                        <div class="flex items-center gap-4">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-900">{{ paymentTypeLabel(p.type) }}</p>
                                                <p class="text-xs text-slate-500">{{ formatDateDMY(p.payment_date) }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right font-outfit font-bold text-emerald-600">
                                            +{{ money(p.amount) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Sticky Payment Form -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-24">
                            <div class="overflow-hidden rounded-[2.5rem] border border-white bg-white/60 p-8 shadow-2xl backdrop-blur-2xl">
                                <h3 class="font-outfit text-2xl font-black tracking-tight text-slate-900">Registrar Pago</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                                    {{ paymentHint }}
                                </p>

                                <form @submit.prevent="submitPayment" class="mt-8 space-y-6">
                                    <!-- Payment Type Info (READ ONLY in this view) -->
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Tipo de operacion</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ payment_types[0]?.label || 'Pago completo' }}</p>
                                    </div>

                                    <!-- Amount Display -->
                                    <div class="relative">
                                        <InputLabel for="amount" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" value="Monto a pagar" />
                                        <div class="relative group">
                                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-bold text-brand-600">$</span>
                                            <TextInput
                                                id="amount"
                                                v-model="payForm.amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                class="block w-full rounded-2xl border-0 bg-white pl-10 pt-4 pb-4 text-xl font-bold shadow-inner ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500"
                                                required
                                                readonly
                                            />
                                        </div>
                                        <InputError class="mt-2" :message="payForm.errors.amount" />
                                    </div>

                                    <!-- Date Select -->
                                    <div>
                                        <InputLabel for="payment_date" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" value="Fecha de pago" />
                                        <TextInput
                                            id="payment_date"
                                            v-model="payForm.payment_date"
                                            type="date"
                                            class="block w-full rounded-2xl border-0 bg-white shadow-inner ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500"
                                            required
                                        />
                                    </div>

                                    <div class="pt-4">
                                        <PrimaryButton 
                                            class="w-full py-4 text-base"
                                            :disabled="payForm.processing || remaining <= 0.01"
                                        >
                                            Confirmar Pago
                                        </PrimaryButton>
                                    </div>
                                </form>

                                <!-- Visual status indicator -->
                                <div v-if="remaining <= 0.01" class="mt-6 flex items-center justify-center gap-2 rounded-2xl bg-emerald-50 p-4 text-emerald-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 4.905-3.513 8.999-8.166 10.024a1 1 0 01-1.668 0C3.513 15.999 0 11.905 0 7c0-.68.056-1.35.166-2.001a1 1 0 01.166-.166c.264-.264.577-.478.92-.625zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-xs font-bold uppercase tracking-widest">Corte liquidado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
