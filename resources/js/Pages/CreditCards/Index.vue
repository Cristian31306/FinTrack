<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PhysicalCard from '@/Components/PhysicalCard.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    creditCards: Array,
});

function destroy(id) {
    if (confirm('¿Eliminar esta tarjeta? Se borrarán todas las compras y cortes asociados.')) {
        router.delete(route('credit-cards.destroy', id), {
            preserveScroll: true
        });
    }
}

const money = (val) => {
    return Number(val).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    });
};
</script>

<template>
    <Head title="Mis Tarjetas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-outfit text-3xl font-extrabold tracking-tight text-slate-900">
                        Mis Tarjetas
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">Administra tus medios de pago y cupos disponibles.</p>
                </div>
                <Link :href="route('credit-cards.create')">
                    <PrimaryButton class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Nueva Tarjeta
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="creditCards?.length" class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="c in creditCards"
                        :key="c.id"
                        class="group relative flex flex-col"
                    >
                        <!-- Physical Card Mockup -->
                        <PhysicalCard 
                            :name="c.name"
                            :franchise="c.franchise"
                            :last4="c.last_4_digits || '0000'"
                            :color="c.color || '#4f46e5'"
                        />

                        <!-- Card Info & Actions -->
                        <div class="mt-6 flex flex-col gap-4 rounded-3xl border border-white bg-white/40 p-6 shadow-premium backdrop-blur-sm transition-all group-hover:bg-white/60">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">Cupo Total</p>
                                    <p class="font-outfit text-xl font-black text-slate-900">{{ money(c.credit_limit) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">Cierre / Pago</p>
                                    <p class="font-outfit text-sm font-bold text-slate-700">Día {{ c.statement_day }} / {{ c.payment_day }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                                <Link
                                    :href="route('credit-cards.edit', c.id)"
                                    class="flex-1 inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 transition-all hover:bg-slate-200"
                                >
                                    Editar Info
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-600 transition-all hover:bg-red-100"
                                    @click="destroy(c.id)"
                                    title="Eliminar Tarjeta"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-[3rem] border border-dashed border-slate-300 bg-white/40 p-16 text-center backdrop-blur-sm">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h3 class="font-outfit text-2xl font-black text-slate-900">No tienes tarjetas registradas</h3>
                    <p class="mt-2 text-slate-500">Agrega tu primera tarjeta para empezar a organizar tus finanzas.</p>
                    <Link :href="route('credit-cards.create')" class="mt-8 inline-flex">
                        <PrimaryButton>Agregar mi primera tarjeta</PrimaryButton>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
