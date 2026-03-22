<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    total_debt: Number,
    cards: Array,
    upcoming_cuts: Array,
    user_share_pending: Number,
    alerts: Array,
});

function money(n) {
    return Number(n).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function upcomingCardLabel(u) {
    return formatCardLabel({
        name: u.card_name,
        last_4_digits: u.card_last_4,
    });
}
</script>

<template>
    <Head title="Panel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Panel FinTrack
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="alerts?.length"
                    class="space-y-2"
                >
                    <div
                        v-for="(a, i) in alerts"
                        :key="i"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        {{ a.message }}
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            Deuda total estimada
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ money(total_debt) }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            Tu parte (compras compartidas)
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ money(user_share_pending) }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-1"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            Acciones rápidas
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link
                                :href="route('purchases.create')"
                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Nueva compra
                            </Link>
                            <Link
                                :href="route('credit-cards.create')"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Nueva tarjeta
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h3 class="text-lg font-medium text-gray-900">
                            Uso por tarjeta
                        </h3>
                        <ul class="mt-4 space-y-4">
                            <li
                                v-for="c in cards"
                                :key="c.id"
                            >
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium text-gray-800">
                                        {{ formatCardLabel(c) }}
                                    </span>
                                    <span class="text-gray-600"
                                        >{{ money(c.debt) }} /
                                        {{ money(c.credit_limit) }}</span
                                    >
                                </div>
                                <div
                                    class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-indigo-500 transition-all"
                                        :style="{
                                            width: `${Math.min(
                                                100,
                                                c.utilization_percent,
                                            )}%`,
                                        }"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ c.utilization_percent }}% del cupo
                                </p>
                                <p
                                    v-if="c.cupo_alert"
                                    class="mt-1.5 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900"
                                >
                                    Alto uso de cupo (≥80%). Considera pagar o
                                    revisar cargos.
                                </p>
                            </li>
                            <li
                                v-if="!cards?.length"
                                class="text-sm text-gray-500"
                            >
                                Aún no hay tarjetas.
                                <Link
                                    :href="route('credit-cards.create')"
                                    class="text-indigo-600 underline"
                                    >Crear una</Link
                                >
                            </li>
                        </ul>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h3 class="text-lg font-medium text-gray-900">
                            Próximo corte a atender
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Si hay un corte ya cerrado sin pagar, se muestra
                            primero; si no, el del cierre más próximo.
                        </p>
                        <ul class="mt-4 divide-y divide-gray-100 text-sm">
                            <li
                                v-for="u in upcoming_cuts"
                                :key="u.cut_id"
                                class="py-3"
                            >
                                <div class="flex justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            {{ upcomingCardLabel(u) }}
                                        </p>
                                        <p class="text-gray-500">
                                            Cierre
                                            {{ formatDateDMY(u.period_end) }} ·
                                            {{ u.status_label }}
                                        </p>
                                        <p
                                            v-if="
                                                u.focus_context ===
                                                'corte_anterior'
                                            "
                                            class="mt-1 text-xs font-medium text-amber-800"
                                        >
                                            Prioridad: saldo de un corte ya
                                            cerrado.
                                        </p>
                                        <p
                                            v-else-if="
                                                u.focus_context ===
                                                'proximo_cierre'
                                            "
                                            class="mt-1 text-xs text-gray-600"
                                        >
                                            Próximo cierre / periodo en curso.
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="font-semibold text-gray-900">
                                            {{ money(u.remaining) }}
                                        </p>
                                        <Link
                                            :href="route('cuts.show', u.cut_id)"
                                            class="text-xs text-indigo-600 hover:underline"
                                        >
                                            Ver corte
                                        </Link>
                                    </div>
                                </div>
                                <details
                                    v-if="u.movements?.length"
                                    class="mt-2 rounded-md border border-gray-100 bg-gray-50/80 px-3 py-2 text-xs"
                                >
                                    <summary
                                        class="cursor-pointer font-medium text-indigo-700 hover:text-indigo-900"
                                    >
                                        Movimientos del corte ({{
                                            u.movements.length
                                        }})
                                    </summary>
                                    <ul class="mt-2 space-y-3 border-t border-gray-200 pt-2">
                                        <li
                                            v-for="(m, mi) in u.movements"
                                            :key="mi"
                                        >
                                            <p class="font-medium text-gray-800">
                                                {{ m.purchase_name }} · Cuota
                                                {{ m.installment_label }}
                                            </p>
                                            <p class="text-gray-600">
                                                {{ money(m.amount) }} · Cierre
                                                de cuota
                                                {{
                                                    formatDateDMY(
                                                        m.statement_close_date,
                                                    )
                                                }}
                                            </p>
                                            <ul
                                                class="ms-3 mt-1 list-disc text-gray-600"
                                            >
                                                <li
                                                    v-for="(party, pi) in m.parties"
                                                    :key="pi"
                                                >
                                                    {{ party.label }}:
                                                    {{ money(party.amount) }}
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </details>
                            </li>
                            <li
                                v-if="!upcoming_cuts?.length"
                                class="py-4 text-gray-500"
                            >
                                Sin saldos pendientes en cortes.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
