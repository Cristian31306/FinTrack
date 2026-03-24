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
    purchase: Object,
    creditCards: Array,
    responsiblePeople: Array,
    categories: Array,
});

const defaultSplitMode = props.purchase.purchase_responsibles?.length 
    ? props.purchase.purchase_responsibles[0].split_type 
    : 'porcentaje';

const splitMode = ref(defaultSplitMode);

const form = useForm({
    credit_card_id: props.purchase.credit_card_id,
    category_id: props.purchase.category_id ?? '',
    name: props.purchase.name,
    total_amount: props.purchase.total_amount,
    installments_count: props.purchase.installments_count,
    purchase_date: props.purchase.purchase_date ? props.purchase.purchase_date.slice(0, 10) : new Date().toISOString().slice(0, 10),
    responsibles: props.purchase.purchase_responsibles ? props.purchase.purchase_responsibles.map(r => ({
        responsible_person_id: r.responsible_person_id,
        split_type: r.split_type,
        split_value: Number(r.split_value),
    })) : [],
});

import * as LucideIcons from 'lucide-vue-next';

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
    form.patch(route('purchases.update', props.purchase.id));
}
</script>

<template>
    <Head title="Editar compra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('purchases.show', purchase.id)"
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Volver</Link
                >
                <h2 class="text-xl font-semibold text-gray-800">
                    Editar: {{ purchase.name }}
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
                        <InputLabel value="Categoría" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                type="button"
                                @click="form.category_id = cat.id"
                                :class="[
                                    'group relative flex h-12 w-12 items-center justify-center rounded-xl transition-all shadow-sm ring-1 ring-slate-100',
                                    form.category_id === cat.id 
                                        ? 'bg-brand-600 text-white shadow-brand-500/25 ring-brand-500 ring-2 scale-110 z-10' 
                                        : 'bg-white hover:bg-brand-50 text-slate-400 hover:text-brand-600'
                                ]"
                                :title="cat.name"
                            >
                                <component 
                                    :is="LucideIcons[cat.icon] || LucideIcons.Tag" 
                                    class="h-6 w-6" 
                                    :style="form.category_id === cat.id ? {} : { color: cat.color }"
                                />
                                <span class="absolute -bottom-10 left-1/2 -translate-x-1/2 z-20 hidden rounded-md bg-slate-800 px-2 py-1 text-[10px] font-bold text-white group-hover:block whitespace-nowrap">
                                    {{ cat.name }}
                                </span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.category_id" />
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
                        <InputError class="mt-2" :message="form.errors.total_amount" />
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
                        <p class="mt-1 text-[11px] text-amber-600 font-medium">
                            <span class="font-bold">Aviso:</span> Si cambias el valor o cuotas, se borrarán las cuotas anteriores y se recalculará desde cero todo el proceso financiero.
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
                        Guardar cambios
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
