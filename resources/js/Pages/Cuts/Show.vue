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
    amount: '',
    type: 'abono',
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
    if (type === 'minimo') {
        payForm.amount =
            props.remaining <= 0.01
                ? ''
                : String(props.suggested_minimum ?? 0);
    } else if (type === 'total') {
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
    if (payForm.type === 'minimo') {
        return `Pago mínimo sugerido (${props.minimum_percent}% del saldo): ${money(props.suggested_minimum)} sobre pendiente ${money(r)}.`;
    }
    if (payForm.type === 'total') {
        return `Se aplicará el saldo total del corte: ${money(r)}.`;
    }
    return `Abono libre. Saldo pendiente del corte: ${money(r)}. Indica el monto que pagarás.`;
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
    <Head title="Detalle corte" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('cuts.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Cortes</Link
                >
                <h2 class="text-xl font-semibold text-gray-800">
                    Corte · {{ formatCardLabel(cut.credit_card) }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <p class="text-sm text-gray-600">
                        Periodo:
                        <strong>{{ formatDateDMY(cut.period_start) }}</strong>
                        al
                        <strong>{{ formatDateDMY(cut.period_end) }}</strong>
                    </p>
                    <p class="mt-2 text-sm text-gray-600">
                        Estado: <strong>{{ cut.status }}</strong>
                    </p>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">Total facturado</p>
                            <p class="text-lg font-semibold">
                                {{ money(cut.total_accrued) }}
                            </p>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">Pagado</p>
                            <p class="text-lg font-semibold">
                                {{ money(paid_total) }}
                            </p>
                        </div>
                        <div class="rounded-md bg-indigo-50 p-3">
                            <p class="text-xs text-indigo-700">Pendiente</p>
                            <p class="text-lg font-semibold text-indigo-900">
                                {{ money(remaining) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        Registrar pago
                    </h3>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ paymentHint }}
                    </p>
                    <p
                        v-if="abonoPreview"
                        class="mt-1 text-sm font-medium text-indigo-800"
                    >
                        {{ abonoPreview }}
                    </p>
                    <form
                        class="mt-4 grid gap-4 sm:grid-cols-2"
                        @submit.prevent="submitPayment"
                    >
                        <div>
                            <InputLabel
                                for="type"
                                value="Tipo de pago"
                            />
                            <select
                                id="type"
                                v-model="payForm.type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option
                                    v-for="t in payment_types"
                                    :key="t.value"
                                    :value="t.value"
                                >
                                    {{ t.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel
                                for="amount"
                                value="Monto"
                            />
                            <TextInput
                                id="amount"
                                v-model="payForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="mt-1 block w-full"
                                required
                                :placeholder="
                                    payForm.type === 'abono'
                                        ? 'Ej. 150000'
                                        : ''
                                "
                            />
                            <InputError
                                class="mt-2"
                                :message="payForm.errors.amount"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel
                                for="payment_date"
                                value="Fecha de pago"
                            />
                            <TextInput
                                id="payment_date"
                                v-model="payForm.payment_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <PrimaryButton :disabled="payForm.processing">
                                Registrar pago
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div
                    v-if="cut.payments?.length"
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        Pagos registrados
                    </h3>
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        <li
                            v-for="p in cut.payments"
                            :key="p.id"
                            class="flex justify-between py-2"
                        >
                            <span
                                >{{ formatDateDMY(p.payment_date) }} ·
                                {{ paymentTypeLabel(p.type) }}</span
                            >
                            <span class="font-medium">{{ money(p.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <div
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        Cuotas en este corte
                    </h3>
                    <table class="mt-4 min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-600">
                                <th class="py-2">Compra</th>
                                <th class="py-2">Cuota</th>
                                <th class="py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ins in cut.installments"
                                :key="ins.id"
                                class="border-b border-gray-50"
                            >
                                <td class="py-2">
                                    <Link
                                        v-if="ins.purchase_id"
                                        :href="
                                            route('purchases.show', ins.purchase_id)
                                        "
                                        class="text-indigo-600 hover:underline"
                                    >
                                        {{ ins.purchase?.name }}
                                    </Link>
                                </td>
                                <td class="py-2">
                                    #{{ ins.installment_number }}
                                </td>
                                <td class="py-2 text-right">
                                    {{ money(ins.total_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
