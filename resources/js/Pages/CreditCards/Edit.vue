<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCardLabel } from '@/utils/cardLabel';
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
});

function submit() {
    form.put(route('credit-cards.update', props.creditCard.id));
}
</script>

<template>
    <Head title="Editar tarjeta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('credit-cards.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Volver</Link
                >
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        Editar tarjeta
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ formatCardLabel(creditCard) }}
                    </p>
                </div>
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
                            for="name"
                            value="Nombre"
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
                            for="franchise"
                            value="Franquicia"
                        />
                        <select
                            id="franchise"
                            v-model="form.franchise"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                            <option
                                v-for="f in franchises"
                                :key="f"
                                :value="f"
                            >
                                {{ f }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel
                            for="last_4_digits"
                            value="Últimos 4 dígitos (opcional)"
                        />
                        <TextInput
                            id="last_4_digits"
                            v-model="form.last_4_digits"
                            type="text"
                            inputmode="numeric"
                            maxlength="4"
                            pattern="[0-9]{0,4}"
                            class="mt-1 block w-full"
                            placeholder="Ej. 4821"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.last_4_digits"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Se muestra junto al nombre en panel, compras y
                            cortes.
                        </p>
                    </div>
                    <div>
                        <InputLabel
                            for="credit_limit"
                            value="Cupo total"
                        />
                        <TextInput
                            id="credit_limit"
                            v-model="form.credit_limit"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="annual_interest_ea"
                            value="Interés EA (%)"
                        />
                        <TextInput
                            id="annual_interest_ea"
                            v-model="form.annual_interest_ea"
                            type="number"
                            step="0.0001"
                            class="mt-1 block w-full"
                            required
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="minimum_payment_percent"
                            value="Pago mínimo del banco (% del saldo)"
                        />
                        <TextInput
                            id="minimum_payment_percent"
                            v-model="form.minimum_payment_percent"
                            type="number"
                            step="0.1"
                            min="0.1"
                            max="100"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.minimum_payment_percent"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="statement_day"
                                value="Día de corte"
                            />
                            <TextInput
                                id="statement_day"
                                v-model="form.statement_day"
                                type="number"
                                min="1"
                                max="31"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="payment_day"
                                value="Día de pago"
                            />
                            <TextInput
                                id="payment_day"
                                v-model="form.payment_day"
                                type="number"
                                min="1"
                                max="31"
                                class="mt-1 block w-full"
                            />
                        </div>
                    </div>
                    <PrimaryButton :disabled="form.processing">
                        Actualizar
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
