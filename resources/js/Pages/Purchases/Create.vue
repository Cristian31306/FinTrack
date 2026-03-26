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
    categories: Array,
});

const splitMode = ref('porcentaje');

const form = useForm({
    credit_card_id: props.creditCards[0]?.id ?? '',
    category_id: props.categories[0]?.id ?? '',
    name: '',
    total_amount: '',
    installments_count: 1,
    purchase_date: new Date().toISOString().slice(0, 10),
    responsibles: [],
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
                    class="group flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm transition-all hover:bg-slate-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h2 class="text-3xl font-black tracking-tighter uppercase text-[#111111]">
                        Registrar Compra
                    </h2>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Documentación de movimiento financiero</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form
                    class="space-y-8 rounded-[2.5rem] bg-white p-8 shadow-premium lg:p-10"
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
                            class="mt-1 block w-full rounded-xl border-black/5 bg-gray-50 px-4 py-3 text-sm font-medium transition-all focus:border-[#C8B07D] focus:ring-[#C8B07D]"
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
                                        ? 'bg-[#C8B07D] text-[#111111] shadow-[#C8B07D]/25 ring-[#C8B07D] ring-2 scale-110 z-10' 
                                        : 'bg-white hover:bg-[#C8B07D]/5 text-slate-400 hover:text-[#C8B07D]'
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
                            class="mt-3 text-[10px] font-black uppercase tracking-widest text-[#C8B07D] hover:underline"
                            @click="addRow"
                        >
                            + Añadir responsable
                        </button>
                        <InputError
                            class="mt-2"
                            :message="form.errors.responsibles"
                        />
                    </div>

                    <PrimaryButton class="w-full justify-center py-4" :disabled="form.processing">
                        Guardar Compra
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
