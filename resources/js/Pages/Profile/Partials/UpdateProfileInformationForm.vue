<script setup>
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = page.props.auth.user;

const name = ref(user.name);
const email = ref(user.email);
const avatar = ref(null);

function onAvatar(e) {
    avatar.value = e.target.files?.[0] ?? null;
}

function submitProfile() {
    const fd = new FormData();
    fd.append('_method', 'patch');
    fd.append('name', name.value);
    fd.append('email', email.value);
    if (avatar.value) {
        fd.append('avatar', avatar.value);
    }
    router.post(route('profile.update'), fd, {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="font-outfit text-2xl font-black tracking-tight text-slate-900">
                Información Personal
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Actualiza tu identidad digital y medios de contacto.
            </p>
        </header>

        <form
            class="mt-8 space-y-8"
            @submit.prevent="submitProfile"
        >
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                <div
                    v-if="user.avatar_url"
                    class="relative"
                >
                    <img
                        :src="user.avatar_url"
                        alt=""
                        class="h-24 w-24 rounded-[2rem] object-cover shadow-lg ring-4 ring-white"
                    />
                    <div class="absolute -bottom-1 -right-1 h-6 w-6 rounded-full border-2 border-white bg-emerald-500"></div>
                </div>
                <div v-else class="flex h-24 w-24 items-center justify-center rounded-[2rem] bg-brand-50 text-brand-600 font-black text-2xl shadow-inner">
                    {{ user.name.charAt(0) }}
                </div>

                <div class="flex-1">
                    <InputLabel
                        for="avatar"
                        value="Foto de perfil"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />
                    <input
                        id="avatar"
                        type="file"
                        accept="image/*"
                        class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-xs file:font-bold file:text-brand-700 hover:file:bg-brand-100 transition-all cursor-pointer"
                        @change="onAvatar"
                    />
                    <InputError
                        class="mt-2"
                        :message="page.props.errors?.avatar"
                    />
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel
                        for="name"
                        value="Nombre Completo"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />

                    <input
                        id="name"
                        v-model="name"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        class="block w-full rounded-2xl border-slate-200 bg-white shadow-sm focus:border-brand-500 focus:ring-brand-500/20 px-4 py-3 transition-all font-medium text-slate-700"
                    />

                    <InputError
                        class="mt-2"
                        :message="page.props.errors?.name"
                    />
                </div>

                <div>
                    <InputLabel
                        for="email"
                        value="Dirección de Correo"
                        class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500"
                    />

                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        autocomplete="username"
                        class="block w-full rounded-2xl border-slate-200 bg-white shadow-sm focus:border-brand-500 focus:ring-brand-500/20 px-4 py-3 transition-all font-medium text-slate-700"
                    />

                    <InputError
                        class="mt-2"
                        :message="page.props.errors?.email"
                    />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-800">
                        Tu correo no está verificado.
                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="ml-1 font-bold text-amber-900 underline hover:text-amber-700"
                        >
                            Reenviar enlace de verificación
                        </Link>
                    </p>

                    <transition
                        enter-active-class="transition duration-300"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                    >
                        <div
                            v-show="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-bold text-emerald-600"
                        >
                            ✓ Se ha enviado un nuevo enlace a tu buzón.
                        </div>
                    </transition>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-8 py-3 text-sm font-bold text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-500 hover:scale-[1.02] active:scale-95 disabled:opacity-50"
                >
                    Guardar Cambios
                </button>

                <transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="page.props.flash?.message"
                        class="text-sm font-bold text-emerald-600"
                    >
                        Guardado.
                    </p>
                </transition>
            </div>
        </form>
    </section>
</template>
