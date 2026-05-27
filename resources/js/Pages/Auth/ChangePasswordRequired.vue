<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.change-required.update'), {
        onFinish: () => form.reset(),
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Troque sua senha" />

    <div class="min-h-screen bg-surface flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-surface-variant border border-border-main rounded-2xl shadow-xl p-8 space-y-6">
            <div>
                <div class="text-4xl mb-2">🔑</div>
                <h1 class="text-2xl font-bold text-text-main">Defina sua senha</h1>
                <p class="text-text-muted text-sm mt-2">
                    Sua conta foi criada com uma senha temporária. Antes de continuar, escolha uma nova senha (mínimo 8 caracteres).
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Nova senha</label>
                    <input v-model="form.password"
                           type="password"
                           autocomplete="new-password"
                           class="input-field w-full"
                           required
                           minlength="8"
                           maxlength="128" />
                    <div v-if="form.errors.password" class="text-trello-red text-xs mt-1">{{ form.errors.password }}</div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Confirmar senha</label>
                    <input v-model="form.password_confirmation"
                           type="password"
                           autocomplete="new-password"
                           class="input-field w-full"
                           required
                           minlength="8"
                           maxlength="128" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="logout" class="text-sm text-text-muted hover:text-text-main">
                        Sair
                    </button>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando…' : 'Confirmar' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
