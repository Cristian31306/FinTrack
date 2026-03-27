<script setup>
import { ref, watch, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { User, Plus, Check, Tag } from 'lucide-vue-next';

const props = defineProps({
    creditCards: Array,
    responsiblePeople: Array,
    categories: Array,
});

const LucideIcons = { User, Plus, Check, Tag };
const splitMode = ref('porcentaje');
const selectedPeopleIds = ref(['me']);

const form = useForm({
    credit_card_id: props.creditCards[0]?.id ?? '',
    category_id: props.categories[0]?.id ?? '',
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

// Helper functions for UX
const isPersonSelected = (id) => selectedPeopleIds.value.includes(id);

const getPersonName = (id) => {
    if (id === 'me') return 'Yo';
    return props.responsiblePeople.find(p => p.id === id)?.name || 'Desconocido';
};

const togglePerson = (person) => {
    if (person.id === 'me') {
        if (isPersonSelected('me')) {
            selectedPeopleIds.value = selectedPeopleIds.value.filter(id => id !== 'me');
        } else {
            selectedPeopleIds.value.push('me');
        }
    } else {
        const idx = form.responsibles.findIndex(r => r.responsible_person_id === person.id);
        if (idx > -1) {
            form.responsibles.splice(idx, 1);
            selectedPeopleIds.value = selectedPeopleIds.value.filter(id => id !== person.id);
        } else {
            form.responsibles.push({
                responsible_person_id: person.id,
                split_type: splitMode.value,
                split_value: ''
            });
            selectedPeopleIds.value.push(person.id);
        }
    }
};

const distributeEqually = () => {
    const count = selectedPeopleIds.value.length;
    if (count === 0) return;

    if (splitMode.value === 'porcentaje') {
        const share = Math.floor(100 / count * 100) / 100;
        form.responsibles.forEach(r => {
            r.split_value = share;
        });
    } else {
        const total = parseFloat(form.total_amount) || 0;
        const share = Math.floor(total / count * 100) / 100;
        form.responsibles.forEach(r => {
            r.split_value = share;
        });
    }
};

const totalAssignedPercent = computed(() => {
    const others = form.responsibles.reduce((acc, r) => acc + Number(r.split_value || 0), 0);
    return isPersonSelected('me') ? 100 : others;
});

const totalAssignedAmount = computed(() => {
    const others = form.responsibles.reduce((acc, r) => acc + Number(r.split_value || 0), 0);
    const total = parseFloat(form.total_amount) || 0;
    return isPersonSelected('me') ? total : others;
});

const getProgressWidth = () => {
    if (splitMode.value === 'porcentaje') return Math.min(100, totalAssignedPercent.value);
    const total = parseFloat(form.total_amount) || 0;
    if (total <= 0) return 0;
    return Math.min(100, (totalAssignedAmount.value / total) * 100);
};

const getProgressBarColor = () => {
    const width = getProgressWidth();
    if (width >= 100) return 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.3)]';
    if (width > 0) return 'bg-[#C8B07D]';
    return 'bg-gray-200';
};

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
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-[#111111] uppercase tracking-tight">
                                    Dividir Cuenta (Opcional)
                                </h3>
                                <p class="mt-0.5 text-[11px] text-gray-500 font-medium">
                                    Selecciona quiénes comparten este gasto contigo
                                </p>
                            </div>
                            <div class="flex bg-gray-100 p-1 rounded-xl">
                                <button 
                                    type="button"
                                    @click="splitMode = 'porcentaje'"
                                    :class="['px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-all', splitMode === 'porcentaje' ? 'bg-white text-[#C8B07D] shadow-sm' : 'text-gray-400']"
                                >
                                    %
                                </button>
                                <button 
                                    type="button"
                                    @click="splitMode = 'monto'"
                                    :class="['px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-all', splitMode === 'monto' ? 'bg-white text-[#C8B07D] shadow-sm' : 'text-gray-400']"
                                >
                                    $
                                </button>
                            </div>
                        </div>

                        <!-- Chips de Selección Rápida -->
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="person in [{ id: 'me', name: 'Yo' }, ...responsiblePeople]"
                                :key="person.id"
                                type="button"
                                @click="togglePerson(person)"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-bold transition-all',
                                    isPersonSelected(person.id)
                                        ? 'bg-[#C8B07D]/10 border-[#C8B07D] text-[#C8B07D] shadow-sm'
                                        : 'bg-white border-gray-200 text-gray-500 hover:border-[#C8B07D]/50'
                                ]"
                            >
                                <component :is="LucideIcons.User" class="h-3.5 w-3.5" />
                                {{ person.name }}
                                <component 
                                    :is="isPersonSelected(person.id) ? LucideIcons.Check : LucideIcons.Plus" 
                                    class="h-3 w-3" 
                                />
                            </button>
                        </div>

                        <!-- Barra de Progreso -->
                        <div v-if="form.responsibles.length > 0" class="mt-6 space-y-2">
                            <div class="flex justify-between text-[10px] font-black uppercase tracking-widest">
                                <span :class="getProgressBarColor().includes('green') ? 'text-green-600' : 'text-gray-400'">
                                    Total Asignado: {{ splitMode === 'porcentaje' ? totalAssignedPercent + '%' : '$' + totalAssignedAmount }}
                                </span>
                                <button 
                                    v-if="form.responsibles.length > 1"
                                    type="button"
                                    @click="distributeEqually"
                                    class="text-[#C8B07D] hover:underline"
                                >
                                    Dividir Equitativamente
                                </button>
                            </div>
                            <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                <div 
                                    class="h-full transition-all duration-500"
                                    :class="getProgressBarColor()"
                                    :style="{ width: getProgressWidth() + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Inputs de Ajuste Fino -->
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="(row, i) in form.responsibles"
                                :key="i"
                                class="flex items-center gap-3 bg-gray-50/50 p-3 rounded-2xl border border-gray-100"
                            >
                                <div class="flex-1">
                                    <span class="text-xs font-bold text-gray-700">{{ getPersonName(row.responsible_person_id) }}</span>
                                </div>
                                <div class="w-32 relative">
                                    <TextInput
                                        v-model="row.split_value"
                                        type="number"
                                        step="0.01"
                                        class="!py-2 !px-3 !text-xs !bg-white border-none shadow-sm focus:ring-1"
                                        :placeholder="splitMode === 'porcentaje' ? '%' : 'Monto'"
                                    />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">
                                        {{ splitMode === 'porcentaje' ? '%' : '$' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <InputError class="mt-2" :message="form.errors.responsibles" />
                    </div>

                    <PrimaryButton class="w-full justify-center py-4" :disabled="form.processing">
                        Guardar Compra
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
