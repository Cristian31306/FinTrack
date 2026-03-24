<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useTransition, TransitionPresets, useMouseInElement } from '@vueuse/core';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

// 💥 estado interactivo
const amount = ref(0);
const installments = ref(1);
const creditLimit = 50000; // Incrementamos a un cupo más estándar para el demo

// 🎡 Lógica 3D (Efecto Tilt)
const targetCard = ref(null);
const { elementX, elementY, elementWidth, elementHeight, isOutside } = useMouseInElement(targetCard);

const cardTransform = computed(() => {
    if (isOutside.value) return 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
    
    const rotateX = (-(elementY.value / elementHeight.value - 0.5) * 20).toFixed(2);
    const rotateY = ((elementX.value / elementWidth.value - 0.5) * 20).toFixed(2);
    
    return `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
});

// ⚡ Números animados (Nivel DIOS)
const animatedAmount = useTransition(amount, { duration: 1000, transition: TransitionPresets.easeOutExpo });
const debt = computed(() => amount.value);
const animatedDebt = useTransition(debt, { duration: 1000, transition: TransitionPresets.easeOutExpo });

// 📈 LÓGICA FINANCIERA REAL (EA)
const interestRateEA = 35.5; // Tasa promedio de tarjetas
const monthlyRate = computed(() => Math.pow(1 + interestRateEA / 100, 1 / 12) - 1);

const totalToPay = computed(() => {
    const P = amount.value || 0;
    const n = installments.value;
    const r = monthlyRate.value;
    if (n <= 1) return P;
    // Fórmula de amortización francesa
    const monthly = (P * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
    return monthly * n;
});

const interestPaid = computed(() => Math.max(0, totalToPay.value - (amount.value || 0)));
const animatedInterest = useTransition(interestPaid, { duration: 1000, transition: TransitionPresets.easeOutExpo });

const monthlyPayment = computed(() => {
    const n = installments.value;
    return totalToPay.value / n;
});
const animatedMonthlyPayment = useTransition(monthlyPayment, { duration: 1000, transition: TransitionPresets.easeOutExpo });

const usage = computed(() => Math.min((debt.value / creditLimit) * 100, 100));
const animatedUsage = useTransition(usage, { duration: 1000, transition: TransitionPresets.easeOutExpo });

// 🧠 Inteligencia Pro
const financialScore = computed(() => {
    let score = 100;
    score -= (usage.value > 80 ? 40 : usage.value / 2);
    score -= (installments.value > 1 ? installments.value * 3 : 0);
    return Math.max(0, Math.min(100, score));
});
const animatedScore = useTransition(financialScore, { duration: 1500, transition: TransitionPresets.easeOutExpo });

// 🧠 Inteligencia Pro (Estilo Jeeves)
const feedback = computed(() => {
    if (usage.value > 95) return { text: 'LIMITE CRÍTICO', color: 'text-rose-400', bg: 'bg-rose-400/5', border: 'border-rose-500/10', ping: 'bg-rose-400', dot: 'bg-rose-500' };
    if (usage.value > 75) return { text: 'USO ELEVADO', color: 'text-amber-400', bg: 'bg-amber-400/5', border: 'border-amber-500/10', ping: 'bg-amber-400', dot: 'bg-amber-500' };
    return { text: 'ESTADO ÓPTIMO', color: 'text-[#C8B07D]', bg: 'bg-[#C8B07D]/5', border: 'border-[#C8B07D]/20', ping: 'bg-[#C8B07D]', dot: 'bg-[#C8B07D]' };
});

const aiTip = computed(() => {
    if (installments.value > 1) {
        return `Análisis de capital: El interés proyectado es de $${Math.round(interestPaid.value).toLocaleString()}. Optar por 1 pago eliminaría este costo financiero por completo.`;
    }
    return 'Estrategia recomendada: La compra a una cuota maximiza tu beneficio sin generar cargos adicionales.';
});

const features = [
    { title: 'Todas tus tarjetas', desc: 'Cupo, franquicia, fechas de corte y pago en un solo lugar.', icon: 'card' },
    { title: 'Compras y cuotas', desc: 'Registra compras al instante. Una cuota sin interés; varias con cálculo según tu EA.', icon: 'installment' },
    { title: 'Quién debe qué', desc: 'Divide gastos por porcentaje o monto y marca lo que te deben.', icon: 'split' },
    { title: 'Cortes y pagos', desc: 'Agrupa cuotas por periodo, registra pagos y ve el saldo real.', icon: 'cut' },
];
</script>

<template>
    <Head title="FinTrack — Controla tus tarjetas" />

    <div
        class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100 antialiased"
    >
        <!-- Fondo Minimalista -->
        <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute w-[800px] h-[800px] bg-[#C8B07D]/5 blur-[160px] top-[-200px] left-[-200px]" />
            <div class="absolute inset-0 bg-black" />
        </div>

        <!-- Nav -->
        <header
            class="relative z-10 border-b border-white/5 bg-slate-950/70 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2.5"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-black text-xl font-black shadow-lg"
                        >F</span
                    >
                    <span
                        class="text-xl font-black tracking-tighter text-white uppercase"
                        >FinTrack</span
                    >
                </Link>

                <nav
                    v-if="canLogin"
                    class="flex items-center gap-2 sm:gap-3"
                >
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/10 transition hover:bg-white/15"
                        >
                            Ir al panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-300 transition hover:text-white"
                        >
                            Ingresar
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/25 transition hover:brightness-110"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section
                class="relative mx-auto max-w-6xl px-4 pb-20 pt-16 sm:px-6 sm:pb-28 sm:pt-24 lg:px-8 lg:pt-28"
            >
                <div class="mx-auto max-w-3xl text-center">
                    <p
                        class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-1.5 text-xs font-medium text-emerald-300"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"
                        />
                        Gestión inteligente de tarjetas
                    </p>
                    <h1
                        class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl"
                    >
                        Tu dinero en tarjeta,
                        <span
                            class="bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300 bg-clip-text text-transparent"
                            >claro y bajo control</span
                        >
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-400 sm:text-xl"
                    >
                        Registra compras en segundos, divide gastos con quien
                        compartes la cuenta y anticipa cuotas y cortes sin
                        hojas de cálculo.
                    </p>
                    <div
                        v-if="canLogin && !$page.props.auth?.user"
                        class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row"
                    >
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-3.5 text-center text-base font-semibold text-slate-950 shadow-xl shadow-emerald-500/30 transition hover:brightness-110 sm:w-auto"
                        >
                            Empezar gratis
                        </Link>
                        <Link
                            :href="route('login')"
                            class="w-full rounded-xl border border-white/15 bg-white/5 px-8 py-3.5 text-center text-base font-semibold text-white backdrop-blur transition hover:bg-white/10 sm:w-auto"
                        >
                            Ya tengo cuenta
                        </Link>
                    </div>
                </div>

                <!-- HERO (INTERACTIVO 🔥) -->
                <div class="relative mx-auto mt-24 max-w-xl group">
                    <div class="absolute -inset-10 blur-[120px] bg-[#C8B07D]/10 rounded-full opacity-50" />

                    <div 
                        ref="targetCard"
                        :style="{ transform: cardTransform }"
                        class="relative rounded-[2.5rem] border border-white/[0.08] bg-black p-10 space-y-10 shadow-[0_32px_64px_-12px_rgba(0,0,0,0.8)] transition-transform duration-300 ease-out overflow-hidden"
                    >
                        <!-- Header Principal -->
                        <div class="flex justify-between items-start">
                            <div class="space-y-1">
                                <h2 class="text-2xl font-black text-white tracking-tighter uppercase italic">Capital Insight</h2>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Global Credit Simulation · EA {{ interestRateEA }}%</p>
                            </div>
                            <div class="bg-white/5 rounded-full px-4 py-1 border border-white/5">
                                <span class="text-[10px] font-black text-[#C8B07D] uppercase tracking-widest">Live Status</span>
                            </div>
                        </div>

                        <!-- INPUTS -->
                        <div class="space-y-8">
                            <div class="space-y-3">
                                <div class="flex justify-between items-end px-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Monto de Operación</label>
                                    <span class="text-xl font-bold text-white tracking-tight">${{ (amount || 0).toLocaleString() }}</span>
                                </div>
                                <div class="relative group/input">
                                    <input
                                        v-model="amount"
                                        type="number"
                                        placeholder="Ingrese monto"
                                        class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white font-medium focus:outline-none focus:border-[#C8B07D]/40 focus:bg-white/[0.05] transition-all"
                                    />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="flex justify-between items-end px-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Plazo (Meses)</label>
                                    <span class="text-xl font-bold text-[#C8B07D] tracking-tight">{{ installments }}</span>
                                </div>
                                <input
                                    type="range"
                                    min="1"
                                    max="36"
                                    v-model="installments"
                                    class="w-full h-1 bg-white/10 rounded-full appearance-none cursor-pointer accent-white"
                                />
                            </div>
                        </div>

                        <!-- RESULTADOS -->
                        <div class="pt-10 border-t border-white/5 grid gap-8">
                            <div class="grid grid-cols-2 gap-8 text-center sm:text-left">
                                <div class="space-y-1">
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Cuota Estimada</p>
                                    <p class="text-3xl font-black text-white tracking-tighter">
                                        ${{ Math.round(animatedMonthlyPayment).toLocaleString() }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Costo Financiero</p>
                                    <p class="text-3xl font-black text-rose-500/90 tracking-tighter">
                                        ${{ Math.round(animatedInterest).toLocaleString() }}
                                    </p>
                                </div>
                            </div>

                            <!-- AI INSIGHT STATUS -->
                            <div 
                                class="rounded-[1.5rem] p-6 border transition-all duration-700"
                                :class="[feedback.bg, feedback.border]"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex h-2 w-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="feedback.ping"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2" :class="feedback.dot"></span>
                                        </div>
                                        <span class="text-[10px] font-black tracking-[0.2em]" :class="feedback.color">{{ feedback.text }}</span>
                                    </div>
                                    <span class="text-[10px] font-black text-white opacity-40 uppercase tracking-widest">Index {{ Math.round(animatedScore) }}%</span>
                                </div>
                                <p class="text-xs font-bold leading-relaxed text-white/80 pr-4">
                                    {{ aiTip }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section
                class="border-t border-white/5 bg-slate-900/40 py-20 backdrop-blur-sm"
            >
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <h2
                        class="text-center text-2xl font-bold text-white sm:text-3xl"
                    >
                        Todo lo que necesitas
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-2xl text-center text-slate-400"
                    >
                        Pensado para uso personal: varias tarjetas, compras a
                        cuotas y saldos compartidos.
                    </p>
                    <ul
                        class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <li
                            v-for="f in features"
                            :key="f.title"
                            class="group rounded-2xl border border-white/10 bg-slate-900/60 p-6 
                            transition duration-300 hover:scale-[1.03] hover:-translate-y-1 
                            hover:border-emerald-400/40 hover:shadow-xl hover:shadow-emerald-500/10"
                        >
                            <div
                                class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500/20 to-teal-600/20 text-emerald-400 ring-1 ring-emerald-500/20"
                            >
                                <svg
                                    v-if="f.icon === 'card'"
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="f.icon === 'installment'"
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="f.icon === 'split'"
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <h3
                                class="text-lg font-semibold text-white group-hover:text-emerald-200"
                            >
                                {{ f.title }}
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                                {{ f.desc }}
                            </p>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- CTA -->
            <section class="py-20">
                <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl">
                        ¿Listo para ordenar tus finanzas?
                    </h2>
                    <p class="mt-3 text-slate-400">
                        Crea tu cuenta y añade tu primera tarjeta en minutos.
                    </p>
                    <div
                        v-if="canLogin && canRegister && !$page.props.auth?.user"
                        class="mt-8"
                    >
                        <Link
                            :href="route('register')"
                            class="inline-flex rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-10 py-4 text-base font-semibold text-slate-950 shadow-xl shadow-emerald-500/25 transition hover:brightness-110"
                        >
                            Crear cuenta gratuita
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer
            class="border-t border-white/5 py-12 text-center"
        >
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-600">
                © {{ new Date().getFullYear() }} FinTrack · Private Equity Lab.
            </p>
        </footer>
    </div>
</template>

<style scoped>
.perspective-1000 {
    perspective: 1000px;
}

input[type="range"]::-webkit-slider-thumb {
    appearance: none;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.2s ease;
}

input[type="range"]::-webkit-slider-thumb:hover {
    transform: scale(1.2);
}
</style>
