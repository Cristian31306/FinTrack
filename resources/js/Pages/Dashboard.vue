<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCardLabel } from '@/utils/cardLabel';
import { formatDateDMY } from '@/utils/dates';
import { Head, Link } from '@inertiajs/vue3';
import * as LucideIcons from 'lucide-vue-next';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'vue-chartjs';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    total_debt: Number,
    cards: Array,
    upcoming_cuts: Array,
    user_share_pending: Number,
    alerts: Array,
    spending_by_category: Array,
});

const chartData = {
    labels: props.spending_by_category.map(c => c.name),
    datasets: [{
        data: props.spending_by_category.map(c => c.amount),
        backgroundColor: props.spending_by_category.map(c => c.color),
        borderWidth: 0,
        hoverOffset: 20
    }]
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        }
    },
    cutout: '75%'
};

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

    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-outfit text-3xl font-extrabold tracking-tight text-slate-900">
                        Bienvenido, {{ $page.props.auth.user.name }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">Aquí tienes un resumen de tus finanzas y próximos pagos.</p>
                </div>
                <div class="hidden sm:flex items-center gap-3">
                    <Link :href="route('credit-cards.create')"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-600 shadow-sm transition-all hover:bg-slate-50 active:scale-95">
                        <component :is="LucideIcons.CreditCard" class="h-5 w-5" />
                        Nueva Tarjeta
                    </Link>
                    <Link :href="route('purchases.create')"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg transition-all hover:bg-brand-500 hover:shadow-brand-500/25 active:scale-95">
                        <component :is="LucideIcons.Plus" class="h-5 w-5" />
                        Nueva Compra
                    </Link>
                </div>
            </div>
        </template>

        <div class="pb-12 pt-4">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Alerts Section -->
                <transition-group enter-active-class="transition duration-300 ease-out"
                    enter-from-class="transform translate-y-4 opacity-0"
                    enter-to-class="transform translate-y-0 opacity-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="transform translate-y-0 opacity-100"
                    leave-to-class="transform translate-y-4 opacity-0" tag="div" class="mb-8 space-y-3">
                    <div v-for="(a, i) in alerts" :key="i"
                        class="flex items-center gap-3 rounded-2xl border border-amber-100 bg-amber-50/80 px-4 py-3 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        {{ a.message }}
                    </div>
                </transition-group>

                <!-- Bento Stats Grid -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        class="group relative overflow-hidden rounded-[2rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:shadow-premium-hover">
                        <div
                            class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-brand-50 opacity-50 transition-transform group-hover:scale-110">
                        </div>
                        <div class="relative">
                            <p class="text-sm font-semibold tracking-wide text-slate-500 uppercase">Deuda Total Estimada
                            </p>
                            <h3 class="mt-2 font-outfit text-4xl font-black tracking-tight text-slate-900">
                                {{ money(total_debt) }}
                            </h3>
                            <div class="mt-6 flex items-center gap-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-brand-500 animate-pulse"></span>
                                <span class="text-xs font-bold text-slate-400 capitalize">Actualizado hace un
                                    momento</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="group relative overflow-hidden rounded-[2rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:shadow-premium-hover md:col-span-1">
                        <div
                            class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-50 opacity-50 transition-transform group-hover:scale-110">
                        </div>
                        <div class="relative">
                            <p class="text-sm font-semibold tracking-wide text-slate-500 uppercase">Tu Parte
                                (Compartidas)</p>
                            <h3 class="mt-2 font-outfit text-4xl font-black tracking-tight text-emerald-600">
                                {{ money(user_share_pending) }}
                            </h3>
                            <div class="mt-6">
                                <Link :href="route('purchases.index')"
                                    class="text-xs font-bold text-brand-600 hover:text-brand-700 transition-colors uppercase tracking-widest">
                                    Ver consumos →</Link>
                            </div>
                        </div>
                    </div>

                    <div
                        class="group relative overflow-hidden rounded-[2rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:shadow-premium-hover md:col-span-1">
                        <div
                            class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-indigo-50 opacity-50 transition-transform group-hover:scale-110">
                        </div>
                        <div class="relative h-full flex flex-col justify-between">
                            <div>
                                <p class="text-sm font-semibold tracking-wide text-slate-500 uppercase">Gasto Mes
                                    (Categorías)
                                </p>
                                <div v-if="spending_by_category.length" class="mt-4 flex gap-6 items-center">
                                    <div class="h-32 w-32 shrink-0">
                                        <Doughnut :data="chartData" :options="chartOptions" />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <div v-for="c in spending_by_category.slice(0, 3)" :key="c.name"
                                            class="flex items-center justify-between">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <component :is="LucideIcons[c.icon] || LucideIcons.Tag"
                                                    class="h-3 w-3 shrink-0" :style="{ color: c.color }" />
                                                <span class="text-[10px] font-bold text-slate-600 truncate">{{ c.name
                                                    }}</span>
                                            </div>
                                            <span class="text-[10px] font-black text-slate-900">{{ money(c.amount)
                                                }}</span>
                                        </div>
                                        <p v-if="spending_by_category.length > 3"
                                            class="text-[8px] font-black text-slate-400 uppercase tracking-widest">+ {{
                                            spending_by_category.length - 3 }} más</p>
                                    </div>
                                </div>
                                <div v-else class="mt-8 text-center py-4">
                                    <p class="text-xs font-bold text-slate-400">Sin gastos este mes aún.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-12 grid grid-cols-1 gap-10 lg:grid-cols-2">
                    <!-- Usage by Card -->
                    <div class="space-y-6">
                        <div class="flex items-center justify-between px-2">
                            <h3 class="font-outfit text-2xl font-black text-slate-900">Uso por Tarjeta</h3>
                            <Link :href="route('credit-cards.index')"
                                class="text-xs font-bold text-brand-600 uppercase tracking-widest hover:underline">
                                Gestionar
                            </Link>
                        </div>
                        <div class="grid gap-4">
                            <div v-for="c in cards" :key="c.id"
                                class="overflow-hidden rounded-3xl border border-white bg-white/40 p-6 shadow-premium backdrop-blur-sm transition-all hover:bg-white/60">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <p class="font-outfit text-lg font-black text-slate-900 tracking-tight">
                                            {{ formatCardLabel(c) }}
                                        </p>
                                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Cupo: {{
                                            money(c.credit_limit) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-outfit text-xl font-bold text-slate-900">{{ money(c.debt) }}</p>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Deuda
                                            Actual
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="relative h-2 w-full overflow-hidden rounded-full bg-slate-100/50 shadow-inner">
                                    <div class="absolute inset-y-0 left-0 rounded-full transition-all duration-1000"
                                        :class="c.utilization_percent >= 80 ? 'bg-amber-500' : 'bg-brand-500'"
                                        :style="{ width: `${Math.min(100, c.utilization_percent)}%` }" />
                                </div>
                                <div
                                    class="mt-3 flex justify-between items-center text-[10px] font-black uppercase tracking-wider">
                                    <span :class="c.utilization_percent >= 80 ? 'text-amber-600' : 'text-slate-400'">
                                        {{ c.utilization_percent }}% Utilizado
                                    </span>
                                    <span v-if="c.cupo_alert"
                                        class="inline-flex items-center gap-x-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                            class="size-4">
                                            <path fill-rule="evenodd"
                                                d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Alto Uso
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Cut Section -->
                    <div class="space-y-6">
                        <div class="flex items-center justify-between px-2">
                            <h3 class="font-outfit text-2xl font-black text-slate-900">Próximo Corte</h3>
                            <Link :href="route('cuts.index')"
                                class="text-xs font-bold text-brand-600 uppercase tracking-widest hover:underline">Ver
                                Todos
                            </Link>
                        </div>

                        <div v-if="upcoming_cuts?.length">
                            <div v-for="u in upcoming_cuts" :key="u.cut_id"
                                class="overflow-hidden rounded-[2.5rem] border border-white bg-white/70 shadow-2xl backdrop-blur-2xl">
                                <div class="p-8">
                                    <div class="flex justify-between items-start mb-8">
                                        <div>
                                            <div :class="[
                                                'mb-3 inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest shadow-sm',
                                                u.focus_context === 'corte_anterior' ? 'bg-amber-100 text-amber-700' : 'bg-brand-100 text-brand-700'
                                            ]">
                                                {{ u.focus_context === 'corte_anterior' ? 'Prioridad: Saldo Pendiente' : 'Cierre Próximo' }}
                                            </div>
                                            <h4 class="font-outfit text-2xl font-black text-slate-900 tracking-tight">
                                                {{ upcomingCardLabel(u) }}
                                            </h4>
                                            <p class="text-sm font-medium text-slate-500">
                                                Cierre: <span class="text-slate-900">{{ formatDateDMY(u.period_end)
                                                    }}</span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-outfit text-3xl font-black text-brand-600">{{
                                                money(u.remaining) }}
                                            </p>
                                            <Link :href="route('cuts.show', u.cut_id)"
                                                class="mt-2 inline-flex font-bold text-xs text-brand-600 uppercase tracking-widest hover:underline">
                                                Pagar ahora →</Link>
                                        </div>
                                    </div>

                                    <!-- Summary by Person (Who owes what) -->
                                    <div v-if="u.summary_by_party?.length"
                                        class="mb-8 rounded-3xl bg-slate-50/50 p-6 shadow-inner ring-1 ring-slate-100">
                                        <p
                                            class="mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                                            Distribución de Deuda</p>
                                        <div class="grid gap-3">
                                            <div v-for="p in u.summary_by_party" :key="p.label"
                                                class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-brand-400"></div>
                                                    <span class="text-sm font-bold text-slate-700">{{ p.label }}</span>
                                                </div>
                                                <span class="font-outfit text-sm font-black text-slate-900">{{
                                                    money(p.amount)
                                                    }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Movement Accordion -->
                                    <details v-if="u.movements?.length" class="group">
                                        <summary
                                            class="flex cursor-pointer list-none items-center justify-between rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 transition-all hover:ring-brand-500/20">
                                            <div class="flex items-center gap-3">
                                                <span
                                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-black text-slate-600">{{
                                                    u.movements.length }}</span>
                                                <span class="text-sm font-bold text-slate-700">Detalle de
                                                    movimientos</span>
                                            </div>
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 text-slate-400 transition-transform group-open:rotate-180"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </summary>
                                        <div
                                            class="mt-4 space-y-4 px-2 pb-2 overflow-hidden transition-all duration-300">
                                            <div v-for="(m, mi) in u.movements" :key="mi"
                                                class="rounded-2xl border border-slate-50 bg-white/50 p-4">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div>
                                                        <p
                                                            class="text-xs font-black uppercase tracking-tight text-slate-900">
                                                            {{
                                                            m.purchase_name }}</p>
                                                        <p class="text-[10px] font-bold text-slate-400">Cuota {{
                                                            m.installment_label }}</p>
                                                    </div>
                                                    <p class="font-outfit text-sm font-bold text-slate-900">{{
                                                        money(m.amount)
                                                        }}</p>
                                                </div>
                                                <div v-if="m.parties.length > 1"
                                                    class="pt-2 border-t border-slate-100 flex flex-wrap gap-x-4 gap-y-1">
                                                    <div v-for="party in m.parties" :key="party.label"
                                                        class="text-[10px] font-bold">
                                                        <span class="text-slate-400">{{ party.label }}:</span>
                                                        <span class="ml-1 text-slate-600">{{ money(party.amount)
                                                            }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-[2.5rem] bg-slate-100/50 p-12 text-center">
                            <div
                                class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-200 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-slate-500 uppercase tracking-widest">Sin cortes pendientes
                            </p>
                            <p class="mt-2 text-xs text-slate-400">Todo está al día por ahora.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
