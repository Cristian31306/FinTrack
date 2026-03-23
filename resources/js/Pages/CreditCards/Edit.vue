<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PhysicalCard from '@/Components/PhysicalCard.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    creditCard: Object,
    franchises: Array,
});

const form = useForm({
    name: props.creditCard.name,
    franchise: props.creditCard.franchise,
    last_4_digits: props.creditCard.last_4_digits ?? '',
    credit_limit: String(props.creditCard.credit_limit),
    annual_interest_ea: String(props.creditCard.annual_interest_ea),
    minimum_payment_percent: String(
        props.creditCard.minimum_payment_percent ?? 5,
    ),
    statement_day: props.creditCard.statement_day,
    payment_day: props.creditCard.payment_day,
    color: props.creditCard.color || '#4f46e5',
});

const colorPresets = [
    '#4f46e5', // Brand Indigo
    '#0f172a', // Slate
    '#b91c1c', // Red
    '#047857', // Emerald
    '#0369a1', // Sky
    '#7e22ce', // Purple
    '#be185d', // Pink
    '#c2410c', // Orange
];

function submit() {
    form.put(route('credit-cards.update', props.creditCard.id));
}
</script>

<template>
    <Head title="Editar Tarjeta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('credit-cards.index')"
                    class="group flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm transition-all hover:bg-slate-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h2 class="font-outfit text-2xl font-black tracking-tight text-slate-900">
                        Editar Tarjeta
                    </h2>
                    <p class="text-sm text-slate-500">Actualiza los detalles de tu medio de pago.</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-2">
                    <!-- Left: Card Preview -->
                    <div class="sticky top-24 self-start">
                        <p class="mb-4 text-xs font-black uppercase tracking-[0.2em] text-slate-400">Vista Previa</p>
                        <PhysicalCard 
                            :name="form.name || 'TU NOMBRE AQUÍ'"
                            :franchise="form.franchise"
                            :last4="form.last_4_digits || '0000'"
                            :color="form.color"
                        />
                        
                        <div class="mt-8 rounded-3xl bg-slate-100 p-6 border border-slate-200">
                            <h4 class="font-bold text-slate-900">Información de la Tarjeta</h4>
                            <p class="mt-1 text-sm text-slate-600 leading-relaxed">
                                Los cambios realizados aquí se reflejarán inmediatamente en tus reportes y cálculos de deudas.
                            </p>
                        </div>
                    </div>

                    <!-- Right: Form -->
                    <form
                        class="space-y-8 rounded-[2.5rem] bg-white p-8 shadow-premium lg:p-10"
                        @submit.prevent="submit"
                    >
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <InputLabel for="name" value="Nombre en la Tarjeta" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    class="block w-full"
                                    placeholder="Ej. Mi Tarjeta Platinum"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="franchise" value="Franquicia" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <select
                                    id="franchise"
                                    v-model="form.franchise"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium transition-all focus:border-brand-500 focus:ring-brand-500"
                                >
                                    <option v-for="f in franchises" :key="f" :value="f">{{ f }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.franchise" />
                            </div>

                            <div>
                                <InputLabel for="last_4_digits" value="Últimos 4 Dígitos" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="last_4_digits"
                                    v-model="form.last_4_digits"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="4"
                                    pattern="[0-9]{0,4}"
                                    class="block w-full"
                                    placeholder="Ej. 4821"
                                />
                                <InputError class="mt-2" :message="form.errors.last_4_digits" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel value="Color de la Tarjeta" class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        v-for="color in colorPresets"
                                        :key="color"
                                        type="button"
                                        class="h-10 w-10 rounded-full border-4 transition-all hover:scale-110"
                                        :style="{ backgroundColor: color, borderColor: form.color === color ? 'white' : 'transparent' }"
                                        :class="{ 'ring-2 ring-slate-200': form.color === color }"
                                        @click="form.color = color"
                                    ></button>
                                    <div class="relative h-10 w-10 overflow-hidden rounded-full border-2 border-slate-200">
                                        <input 
                                            type="color" 
                                            v-model="form.color"
                                            class="absolute -inset-2 h-14 w-14 cursor-pointer"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div>
                                <InputLabel for="credit_limit" value="Cupo Total" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="credit_limit"
                                    v-model="form.credit_limit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.credit_limit" />
                            </div>

                            <div>
                                <InputLabel for="annual_interest_ea" value="Interés E.A. (%)" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="annual_interest_ea"
                                    v-model="form.annual_interest_ea"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    class="block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.annual_interest_ea" />
                            </div>

                            <div>
                                <InputLabel for="statement_day" value="Día de Corte" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="statement_day"
                                    v-model="form.statement_day"
                                    type="number"
                                    min="1"
                                    max="31"
                                    class="block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.statement_day" />
                            </div>

                            <div>
                                <InputLabel for="payment_day" value="Día de Pago" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="payment_day"
                                    v-model="form.payment_day"
                                    type="number"
                                    min="1"
                                    max="31"
                                    class="block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.payment_day" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="minimum_payment_percent" value="% Pago Mínimo Sugerido" class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500" />
                                <TextInput
                                    id="minimum_payment_percent"
                                    v-model="form.minimum_payment_percent"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    max="100"
                                    class="block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.minimum_payment_percent" />
                            </div>
                        </div>

                        <div class="pt-6">
                            <PrimaryButton class="w-full justify-center py-4" :disabled="form.processing">
                                Guardar Cambios
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
