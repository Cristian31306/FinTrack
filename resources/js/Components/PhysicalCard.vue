<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    id: { type: [Number, String], required: false },
    name: { type: String, required: true },
    franchise: { type: String, required: true },
    last4: { type: String, required: true },
    color: { type: String, default: '#4f46e5' },
    creditLimit: { type: [Number, String], default: 0 },
    statementDay: { type: [Number, String], default: 1 },
    paymentDay: { type: [Number, String], default: 1 },
    isMockup: { type: Boolean, default: false }
});

const emit = defineEmits(['delete']);

const formattedNumber = computed(() => {
    return `•••• •••• •••• ${props.last4}`;
});

const money = (val) => {
    return Number(val).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    });
};

const franchiseLogo = computed(() => {
    const f = props.franchise.toLowerCase();
    if (f.includes('visa')) return 'visa';
    if (f.includes('master')) return 'mastercard';
    if (f.includes('amex') || f.includes('american')) return 'amex';
    return 'generic';
});

// Calculate if color is light or dark to adjust text color
function getContrastYIQ(hexcolor){
    hexcolor = hexcolor.replace("#", "");
    var r = parseInt(hexcolor.substr(0,2),16);
    var g = parseInt(hexcolor.substr(2,2),16);
    var b = parseInt(hexcolor.substr(4,2),16);
    var yiq = ((r*299)+(g*587)+(b*114))/1000;
    return (yiq >= 128) ? 'text-slate-900' : 'text-white';
}

const textColorClass = computed(() => getContrastYIQ(props.color));
const secondaryTextColorClass = computed(() => {
    const isLight = getContrastYIQ(props.color) === 'text-slate-900';
    return isLight ? 'text-slate-500' : 'text-white/60';
});
</script>

<template>
    <div 
        class="group/card relative aspect-[1.586/1] w-full overflow-hidden rounded-3xl p-6 shadow-2xl transition-all hover:scale-[1.02] hover:shadow-brand-500/20"
        :style="{ backgroundColor: color }"
        :class="textColorClass"
    >
        <!-- Card Pattern/Texture Overlay -->
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>

        <div class="relative h-full flex flex-col justify-between">
            <!-- Top Row: Franchise & Chip & Actions -->
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-4">
                    <div class="h-10 w-12 rounded-lg bg-gradient-to-br from-yellow-200 to-yellow-500 opacity-90 shadow-inner flex items-center justify-center border border-yellow-600/20">
                        <!-- Chip Lines -->
                        <div class="grid grid-cols-2 gap-px w-full h-full p-2 opacity-40">
                            <div class="border-r border-b border-black/20"></div>
                            <div class="border-b border-black/20"></div>
                            <div class="border-r border-black/20"></div>
                            <div></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Actions (Hidden by default, visible on hover) -->
                    <div v-if="!isMockup" class="flex items-center gap-2 opacity-0 transition-all duration-300 group-hover/card:opacity-100 -translate-y-2 group-hover/card:translate-y-0">
                        <Link
                            :href="route('credit-cards.edit', id)"
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 backdrop-blur-md transition-all hover:bg-white/40"
                            title="Editar"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </Link>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-red-500/20 backdrop-blur-md transition-all hover:bg-red-500/40"
                            @click="$emit('delete', id)"
                            title="Eliminar"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <!-- Franchise Logo -->
                    <div class="text-right ml-2">
                        <div v-if="franchiseLogo === 'visa'" class="font-outfit text-2xl font-black italic tracking-tighter opacity-90">
                            VISA
                        </div>
                        <div v-else-if="franchiseLogo === 'mastercard'" class="flex items-center gap-1 opacity-90">
                            <div class="h-6 w-6 rounded-full bg-red-500/80"></div>
                            <div class="h-6 w-6 rounded-full bg-yellow-500/80 -ml-3"></div>
                            <span class="text-[10px] font-bold ml-1">mastercard</span>
                        </div>
                        <div v-else-if="franchiseLogo === 'amex'" class="h-8 w-12 bg-blue-500/20 border border-white/20 rounded flex items-center justify-center">
                            <span class="text-[10px] font-black italic">AMEX</span>
                        </div>
                        <div v-else class="text-xs font-bold uppercase tracking-widest opacity-70">
                            {{ franchise }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle: Cupo & Number -->
            <div class="flex flex-col gap-1">
                <div v-if="!isMockup">
                    <span class="text-[9px] font-bold uppercase tracking-[0.2em] opacity-60 mb-1 block">Cupo Total</span>
                    <p class="font-outfit text-2xl font-black tracking-tight leading-none">
                        {{ money(creditLimit) }}
                    </p>
                </div>
                <div class="mt-4">
                    <p class="font-mono text-xl tracking-[0.2em] opacity-90 sm:text-2xl">
                        {{ formattedNumber }}
                    </p>
                </div>
            </div>

            <!-- Bottom: Name & Info -->
            <div class="flex justify-between items-end">
                <div class="max-w-[150px]">
                    <span class="text-[8px] font-bold uppercase tracking-widest opacity-60 mb-1 block">Titular</span>
                    <p class="font-outfit text-sm font-black uppercase tracking-widest truncate">
                        {{ name }}
                    </p>
                </div>
                <div v-if="!isMockup" class="flex gap-6 text-right">
                    <div>
                        <span class="text-[8px] font-bold uppercase tracking-widest opacity-60 mb-1 block">Cierre</span>
                        <p class="font-mono text-xs font-bold">Día {{ statementDay }}</p>
                    </div>
                    <div>
                        <span class="text-[8px] font-bold uppercase tracking-widest opacity-60 mb-1 block">Pago</span>
                        <p class="font-mono text-xs font-bold">Día {{ paymentDay }}</p>
                    </div>
                </div>
                <div v-else class="text-right">
                    <span class="text-[8px] font-bold uppercase tracking-widest opacity-60 mb-1 block">Vence</span>
                    <p class="font-mono text-sm font-bold">12/28</p>
                </div>
            </div>
        </div>

        <!-- Glossy Reflection -->
        <div class="absolute -top-1/2 -left-1/2 w-[200%] h-[200%] bg-gradient-to-tr from-transparent via-white/5 to-transparent rotate-12 pointer-events-none"></div>
    </div>
</template>

<style scoped>
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}
</style>
