<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    purchase: Object,
});

const form = useForm({
    name: props.purchase.name,
    purchase_date: props.purchase.purchase_date?.slice?.(0, 10) ?? props.purchase.purchase_date,
});

function money(n) {
    return Number(n).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function saveBasics() {
    form.patch(route('purchases.update', props.purchase.id));
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
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Volver</Link
                >
                <h2 class="text-xl font-semibold text-gray-800">
                    {{ purchase.name }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <p class="text-sm text-gray-600">
                        Tarjeta:
                        <strong>{{
                            formatCardLabel(purchase.credit_card)
                        }}</strong>
                        · Total:
                        <strong>{{ money(purchase.total_amount) }}</strong>
                        · Cuotas: {{ purchase.installments_count }}
                    </p>
                    <form
                        class="mt-4 grid gap-4 sm:grid-cols-2"
                        @submit.prevent="saveBasics"
                    >
                        <div>
                            <InputLabel
                                for="name"
                                value="Nombre"
                            />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                class="mt-1 block w-full"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.name"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="purchase_date"
                                value="Fecha"
                            />
                            <TextInput
                                id="purchase_date"
                                v-model="form.purchase_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <PrimaryButton :disabled="form.processing"
                                >Guardar cambios</PrimaryButton
                            >
                        </div>
                    </form>
                </div>

                <div
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        Cuotas
                    </h3>
                    <table class="mt-4 min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-600">
                                <th class="py-2">#</th>
                                <th class="py-2">Capital</th>
                                <th class="py-2">Interés</th>
                                <th class="py-2">Total</th>
                                <th class="py-2">Cierre estado</th>
                                <th class="py-2">Corte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ins in purchase.installments"
                                :key="ins.id"
                                class="border-b border-gray-50"
                            >
                                <td class="py-2">{{ ins.installment_number }}</td>
                                <td class="py-2">{{ money(ins.principal_amount) }}</td>
                                <td class="py-2">{{ money(ins.interest_amount) }}</td>
                                <td class="py-2 font-medium">
                                    {{ money(ins.total_amount) }}
                                </td>
                                <td class="py-2 text-gray-600">
                                    {{
                                        formatDateDMY(ins.statement_close_date)
                                    }}
                                </td>
                                <td class="py-2">
                                    <Link
                                        v-if="ins.cut_id"
                                        :href="route('cuts.show', ins.cut_id)"
                                        class="text-indigo-600 hover:underline"
                                        >Ver</Link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="purchase.purchase_responsibles?.length"
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        Responsables
                    </h3>
                    <ul class="mt-4 divide-y divide-gray-100 text-sm">
                        <li
                            v-for="pr in purchase.purchase_responsibles"
                            :key="pr.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-3"
                        >
                            <div>
                                <p class="font-medium text-gray-800">
                                    {{ pr.responsible_person?.name }}
                                </p>
                                <p class="text-gray-600">
                                    Debe: {{ money(pr.owed_amount) }} ·
                                    {{ pr.split_type }}:
                                    {{ pr.split_value }}
                                </p>
                                <p
                                    class="text-xs"
                                    :class="
                                        pr.status === 'pagado'
                                            ? 'text-green-600'
                                            : 'text-amber-600'
                                    "
                                >
                                    {{ pr.status }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    v-if="pr.status !== 'pagado'"
                                    type="button"
                                    class="rounded bg-gray-800 px-3 py-1 text-xs text-white hover:bg-gray-700"
                                    @click="markPaid(pr)"
                                >
                                    Marcar pagado
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                    @click="markPending(pr)"
                                >
                                    Marcar pendiente
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
