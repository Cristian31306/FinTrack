<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as LucideIcons from 'lucide-vue-next';

const props = defineProps({
    categories: Array
});

const isModalOpen = ref(false);
const editingCategory = ref(null);

const form = useForm({
    name: '',
    icon: 'tag',
    color: '#64748b'
});

const icons = [
    'Utensils', 'Car', 'Zap', 'Gamepad2', 'ShoppingBag', 
    'Home', 'Heart', 'Coffee', 'Briefcase', 'Music', 
    'Smartphone', 'Plane', 'Gift', 'Wallet', 'CreditCard', 
    'Trophy', 'Activity', 'Bus', 'Pizza', 'Stethoscope'
];

function openModal(category = null) {
    editingCategory.value = category;
    if (category) {
        form.name = category.name;
        form.icon = category.icon;
        form.color = category.color;
    } else {
        form.reset();
    }
    isModalOpen.value = true;
}

function submit() {
    if (editingCategory.value) {
        form.put(route('categories.update', editingCategory.value.id), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('categories.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            }
        });
    }
}

function deleteCategory(id) {
    if (confirm('¿Estás seguro de eliminar esta categoría?')) {
        form.delete(route('categories.destroy', id));
    }
}
</script>

<template>
    <Head title="Categorías" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-black tracking-tighter uppercase text-[#111111]">
                        Gestionar Categorías
                    </h2>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Personalización del ecosistema financiero</p>
                </div>
                <button
                    @click="openModal()"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#C8B07D] px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-[#111111] shadow-lg transition-all hover:bg-[#A68F5B] hover:shadow-[#C8B07D]/25 active:scale-95"
                >
                    <component :is="LucideIcons.Plus" class="h-4 w-4" />
                    Nueva Categoría
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="c in categories"
                        :key="c.id"
                        class="group relative overflow-hidden rounded-[2rem] border border-white bg-white/60 p-6 shadow-premium backdrop-blur-xl transition-all hover:bg-white/80"
                    >
                        <div class="flex items-center gap-4">
                            <div 
                                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl shadow-inner transition-transform group-hover:scale-110"
                                :style="{ backgroundColor: c.color + '20', color: c.color }"
                            >
                                <component :is="LucideIcons[c.icon] || LucideIcons.Tag" class="h-8 w-8" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-outfit text-xl font-bold text-slate-900">{{ c.name }}</h3>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    {{ c.user_id ? 'Personalizada' : 'Sistema' }}
                                </p>
                            </div>
                            <div v-if="c.user_id" class="flex gap-2 opacity-100 md:opacity-0 transition-opacity md:group-hover:opacity-100">
                                <button @click="openModal(c)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-[#C8B07D]">
                                    <component :is="LucideIcons.Pencil" class="h-5 w-5" />
                                </button>
                                <button @click="deleteCategory(c.id)" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">
                                    <component :is="LucideIcons.Trash2" class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm sm:p-6">
            <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-[2rem] bg-white shadow-2xl transition-all custom-scrollbar">
                <div class="p-6">
                    <h3 class="font-outfit text-xl font-black text-slate-900">
                        {{ editingCategory ? 'Editar Categoría' : 'Nueva Categoría' }}
                    </h3>
                    
                    <form @submit.prevent="submit" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Nombre</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-xl border-slate-100 bg-slate-50 p-3 text-sm font-bold shadow-inner ring-1 ring-slate-200 transition-all focus:border-brand-500 focus:bg-white focus:ring-brand-500/20"
                                placeholder="Ej: Comida, Transporte..."
                            />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Color</label>
                            <div class="mt-1 flex items-center gap-3">
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="h-10 w-10 cursor-pointer overflow-hidden rounded-lg border-none p-0 shadow-sm"
                                />
                                <span class="text-[10px] font-mono font-bold text-slate-400">{{ form.color }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Icono</label>
                            <div class="mt-2 grid grid-cols-4 gap-2 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100 shadow-inner sm:grid-cols-5">
                                <button
                                    v-for="iconName in icons"
                                    :key="iconName"
                                    type="button"
                                    @click="form.icon = iconName"
                                    :class="[
                                        'flex h-10 w-10 items-center justify-center rounded-lg transition-all',
                                        form.icon === iconName 
                                            ? 'bg-[#C8B07D] text-[#111111] shadow-lg scale-105' 
                                            : 'hover:bg-[#C8B07D]/10 hover:text-[#C8B07D] text-slate-400'
                                    ]"
                                >
                                    <component :is="LucideIcons[iconName]" class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="isModalOpen = false"
                                class="flex-1 rounded-xl bg-slate-100 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-200"
                            >
                                Cancelar
                            </button>
                             <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex-1 rounded-xl bg-[#111111] py-3 text-[10px] font-black uppercase tracking-widest text-[#C8B07D] shadow-lg transition-all hover:bg-black disabled:opacity-50"
                            >
                                {{ editingCategory ? 'Guardar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
