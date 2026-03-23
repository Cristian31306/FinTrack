<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    franchise: { type: String, required: true },
    last4: { type: String, required: true },
    color: { type: String, default: '#4f46e5' },
    isMockup: { type: Boolean, default: false }
});

const formattedNumber = computed(() => {
    return `•••• •••• •••• ${props.last4}`;
});

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
</script>

<template>
    <div 
        class="relative aspect-[1.586/1] w-full overflow-hidden rounded-2xl p-6 shadow-2xl transition-all hover:scale-[1.02] hover:shadow-brand-500/20"
        :style="{ backgroundColor: color }"
        :class="textColorClass"
    >
        <!-- Card Pattern/Texture Overlay -->
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>

        <div class="relative h-full flex flex-col justify-between">
            <!-- Top Row: Franchise & Chip -->
            <div class="flex justify-between items-start">
                <div class="h-10 w-12 rounded-lg bg-gradient-to-br from-yellow-200 to-yellow-500 opacity-90 shadow-inner flex items-center justify-center border border-yellow-600/20">
                    <!-- Chip Lines -->
                    <div class="grid grid-cols-2 gap-px w-full h-full p-2 opacity-40">
                        <div class="border-r border-b border-black/20"></div>
                        <div class="border-b border-black/20"></div>
                        <div class="border-r border-black/20"></div>
                        <div></div>
                    </div>
                </div>

                <div class="text-right">
                    <!-- Franchise Logos (SVGs) -->
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

            <!-- Middle: Card Number -->
            <div class="mt-4">
                <p class="font-mono text-xl tracking-[0.2em] opacity-90 sm:text-2xl lg:text-3xl">
                    {{ formattedNumber }}
                </p>
            </div>

            <!-- Bottom: Name & Exp -->
            <div class="flex justify-between items-end">
                <div>
                    <span class="text-[8px] font-bold uppercase tracking-widest opacity-60 mb-1 block">Titular</span>
                    <p class="font-outfit text-sm font-black uppercase tracking-widest truncate max-w-[180px]">
                        {{ name }}
                    </p>
                </div>
                <div class="text-right">
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
    font-family: 'Courier New', Courier, monospace;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}
</style>
