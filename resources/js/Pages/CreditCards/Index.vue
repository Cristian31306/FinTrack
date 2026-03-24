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
                            :id="c.id"
                            :name="c.name"
                            :franchise="c.franchise"
                            :last4="c.last_4_digits || '0000'"
                            :color="c.color || '#4f46e5'"
                            :credit-limit="c.credit_limit"
                            :statement-day="c.statement_day"
                            :payment-day="c.payment_day"
                            @delete="destroy"
                        />
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
