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
            <h2 class="text-lg font-medium text-gray-900">
                Datos del perfil
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Actualiza tu nombre, correo y foto.
            </p>
        </header>

        <form
            class="mt-6 space-y-6"
            @submit.prevent="submitProfile"
        >
            <div
                v-if="user.avatar_url"
                class="flex items-center gap-3"
            >
                <img
                    :src="user.avatar_url"
                    alt=""
                    class="h-16 w-16 rounded-full object-cover ring-2 ring-gray-200"
                />
            </div>

            <div>
                <InputLabel
                    for="avatar"
                    value="Foto de perfil"
                />
                <input
                    id="avatar"
                    type="file"
                    accept="image/*"
                    class="mt-1 block w-full text-sm text-gray-600 file:me-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                    @change="onAvatar"
                />
                <InputError
                    class="mt-2"
                    :message="page.props.errors?.avatar"
                />
            </div>

            <div>
                <InputLabel
                    for="name"
                    value="Nombre"
                />

                <input
                    id="name"
                    v-model="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />

                <InputError
                    class="mt-2"
                    :message="page.props.errors?.name"
                />
            </div>

            <div>
                <InputLabel
                    for="email"
                    value="Correo"
                />

                <input
                    id="email"
                    v-model="email"
                    type="email"
                    required
                    autocomplete="username"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />

                <InputError
                    class="mt-2"
                    :message="page.props.errors?.email"
                />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Tu correo no está verificado.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Reenviar enlace
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Se envió un nuevo enlace a tu correo.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Guardar
                </button>
            </div>
        </form>
    </section>
</template>
