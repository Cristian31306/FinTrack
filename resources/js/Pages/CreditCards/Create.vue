<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    franchises: Array,
});

const form = useForm({
    name: '',
    franchise: 'Visa',
    last_4_digits: '',
    credit_limit: '',
    annual_interest_ea: '0',
    minimum_payment_percent: '5',
    statement_day: 15,
    payment_day: 5,
});

function submit() {
    form.post(route('credit-cards.store'));
}
</script>

<template>
    <Head title="Nueva tarjeta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('credit-cards.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                    >← Volver</Link
                >
                <h2 class="text-xl font-semibold text-gray-800">
                    Nueva tarjeta
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
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="f in franchises"
                                :key="f"
                                :value="f"
                            >
                                {{ f }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="form.errors.franchise"
                        />
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
                            min="0"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.credit_limit"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="annual_interest_ea"
                            value="Interés efectivo anual (%)"
                        />
                        <TextInput
                            id="annual_interest_ea"
                            v-model="form.annual_interest_ea"
                            type="number"
                            step="0.0001"
                            min="0"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.annual_interest_ea"
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
                        <p class="mt-1 text-xs text-gray-500">
                            Se usa para sugerir el mínimo al registrar pagos en
                            un corte.
                        </p>
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
                                required
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.statement_day"
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
                                required
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.payment_day"
                            />
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <PrimaryButton :disabled="form.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
