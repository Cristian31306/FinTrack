<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
</script>

<template>
    <Head title="Mi Perfil" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="font-outfit text-3xl font-extrabold tracking-tight text-slate-900">
                    Configuración de Perfil
                </h2>
                <p class="mt-1 text-sm text-slate-500">Gestiona tu información personal, seguridad y cuenta.</p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">
                <!-- Informacion del Perfil -->
                <div class="overflow-hidden rounded-[2.5rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:bg-white/70">
                    <div class="max-w-2xl">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </div>
                </div>

                <!-- Seguridad / Password -->
                <div class="overflow-hidden rounded-[2.5rem] border border-white bg-white/60 p-8 shadow-premium backdrop-blur-xl transition-all hover:bg-white/70">
                    <div class="max-w-2xl">
                        <UpdatePasswordForm />
                    </div>
                </div>

                <!-- Peligro / Delete Account -->
                <div class="overflow-hidden rounded-[2.5rem] border border-red-50 bg-red-50/10 p-8 shadow-premium backdrop-blur-xl transition-all hover:bg-red-50/20">
                    <div class="max-w-2xl">
                        <DeleteUserForm />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
