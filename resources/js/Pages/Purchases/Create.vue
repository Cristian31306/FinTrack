<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    creditCards: Array,
    responsiblePeople: Array,
});

const splitMode = ref('porcentaje');

const form = useForm({
    credit_card_id: props.creditCards[0]?.id ?? '',
    name: '',
    total_amount: '',
    installments_count: 1,
    purchase_date: new Date().toISOString().slice(0, 10),
    responsibles: [],
});

watch(splitMode, (mode) => {
    form.responsibles = form.responsibles.map((r) => ({
        ...r,
        split_type: mode,
        split_value: '',
    }));
});

function addRow() {
    form.responsibles.push({
        responsible_person_id: '',
        split_type: splitMode.value,
        split_value: '',
    });
}

function removeRow(i) {
    form.responsibles.splice(i, 1);
}

function submit() {
    form.responsibles = form.responsibles.filter(
        (r) => r.responsible_person_id && r.split_value !== '' && r.split_value != null,
    );
    form.post(route('purchases.store'));
}
</script>

<template>
    <Head title="Nueva compra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('purchases.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Volver</Link
                >
                <h2 class="text-xl font-semibold text-gray-800">
                    Registrar compra
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form
                    class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel
                            for="credit_card_id"
                            value="Tarjeta"
                        />
                        <select
                            id="credit_card_id"
                            v-model="form.credit_card_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            required
                        >
                            <option
                                v-for="c in creditCards"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ formatCardLabel(c) }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="form.errors.credit_card_id"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="name"
                            value="Nombre de la compra"
                        />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.name"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="total_amount"
                            value="Valor total"
                        />
                        <TextInput
                            id="total_amount"
                            v-model="form.total_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="installments_count"
                            value="Número de cuotas"
                        />
                        <TextInput
                            id="installments_count"
                            v-model="form.installments_count"
                            type="number"
                            min="1"
                            max="360"
                            class="mt-1 block w-full"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Una cuota: sin interés. Varias: interés según EA de
                            la tarjeta (modelo simplificado).
                        </p>
                    </div>
                    <div>
                        <InputLabel
                            for="purchase_date"
                            value="Fecha de compra"
                        />
                        <TextInput
                            id="purchase_date"
                            v-model="form.purchase_date"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <h3 class="font-medium text-gray-900">
                            Responsables (opcional)
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Porcentajes deben sumar 100%. Montos no pueden
                            superar el total.
                        </p>
                        <div class="mt-2 flex gap-4 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="splitMode"
                                    type="radio"
                                    value="porcentaje"
                                />
                                Porcentaje
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="splitMode"
                                    type="radio"
                                    value="monto"
                                />
                                Monto fijo
                            </label>
                        </div>
                        <div
                            v-for="(row, i) in form.responsibles"
                            :key="i"
                            class="mt-3 grid gap-2 sm:grid-cols-12 sm:items-end"
                        >
                            <div class="sm:col-span-6">
                                <select
                                    v-model="row.responsible_person_id"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                    required
                                >
                                    <option value="">
                                        Seleccionar persona
                                    </option>
                                    <option
                                        v-for="p in responsiblePeople"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <TextInput
                                    v-model="row.split_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="block w-full text-sm"
                                    :placeholder="
                                        splitMode === 'porcentaje'
                                            ? '%'
                                            : 'Monto'
                                    "
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <button
                                    type="button"
                                    class="text-sm text-red-600 hover:underline"
                                    @click="removeRow(i)"
                                >
                                    Quitar
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-3 text-sm text-indigo-600 hover:underline"
                            @click="addRow"
                        >
                            + Añadir responsable
                        </button>
                        <InputError
                            class="mt-2"
                            :message="form.errors.responsibles"
                        />
                    </div>

                    <PrimaryButton :disabled="form.processing">
                        Guardar compra
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
