<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});

const features = [
    {
        title: 'Todas tus tarjetas',
        desc: 'Cupo, franquicia, fechas de corte y pago en un solo lugar.',
        icon: 'card',
    },
    {
        title: 'Compras y cuotas',
        desc: 'Registra compras al instante. Una cuota sin interés; varias con cálculo según tu EA.',
        icon: 'installment',
    },
    {
        title: 'Quién debe qué',
        desc: 'Divide gastos por porcentaje o monto y marca lo que te deben.',
        icon: 'split',
    },
    {
        title: 'Cortes y pagos',
        desc: 'Agrupa cuotas por periodo, registra pagos y ve el saldo real.',
        icon: 'cut',
    },
];
</script>

<template>
    <Head title="FinTrack — Controla tus tarjetas" />

    <div
        class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100 antialiased"
    >
        <!-- Fondo -->
        <div
            class="pointer-events-none fixed inset-0 -z-10"
            aria-hidden="true"
        >
            <div
                class="absolute -left-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-emerald-500/25 blur-[100px]"
            />
            <div
                class="absolute -right-20 top-1/4 h-[32rem] w-[32rem] rounded-full bg-indigo-600/20 blur-[110px]"
            />
            <div
                class="absolute bottom-0 left-1/3 h-[24rem] w-[24rem] rounded-full bg-teal-500/15 blur-[90px]"
            />
            <div
                class="absolute inset-0 bg-[linear-gradient(to_bottom,transparent_0%,rgb(2,6,23)_55%,rgb(2,6,23)_100%)]"
            />
            <div
                class="absolute inset-0 opacity-[0.12]"
                style="
                    background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);
                "
            />
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
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-teal-600 text-lg font-black text-slate-950 shadow-lg shadow-emerald-500/20"
                        >F</span
                    >
                    <span
                        class="text-lg font-bold tracking-tight text-white"
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

                <!-- Tarjeta visual -->
                <div
                    class="relative mx-auto mt-16 max-w-lg sm:mt-20 lg:max-w-xl"
                >
                    <div
                        class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-emerald-500/20 via-teal-500/10 to-indigo-500/20 blur-2xl"
                    />
                    <div
                        class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-slate-800/90 to-slate-900/95 p-6 shadow-2xl ring-1 ring-white/5 backdrop-blur-sm sm:p-8"
                    >
                        <div
                            class="flex items-start justify-between gap-4 border-b border-white/10 pb-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wider text-slate-500"
                                >
                                    Deuda estimada
                                </p>
                                <p
                                    class="mt-1 text-3xl font-bold tabular-nums text-white"
                                >
                                    $ 0.00
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300"
                            >
                                Demo
                            </div>
                        </div>
                        <div class="mt-5 space-y-3">
                            <div
                                class="flex justify-between text-sm text-slate-400"
                            >
                                <span>Uso de cupo</span>
                                <span class="text-emerald-400">0%</span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-slate-700/80"
                            >
                                <div
                                    class="h-full w-[35%] rounded-full bg-gradient-to-r from-emerald-400 to-teal-500"
                                />
                            </div>
                            <div
                                class="flex justify-between pt-2 text-xs text-slate-500"
                            >
                                <span>Próximo corte</span>
                                <span class="text-slate-300">—</span>
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
                            class="group rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:border-emerald-500/30 hover:bg-slate-800/60"
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
            class="border-t border-white/5 py-8 text-center text-sm text-slate-500"
        >
            <p>© {{ new Date().getFullYear() }} FinTrack. Todos los derechos reservados.</p>
        </footer>
    </div>
</template>
