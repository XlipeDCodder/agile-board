<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Entrar - B-Agile" />

        <div class="w-full max-w-md mx-auto animate-fade-in">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand/80 shadow-lg">
                        <span class="text-xl font-bold text-white">B</span>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-text-main">Bem-vindo de volta</h1>
                <p class="mt-2 text-text-muted">Faça login na sua conta B-Agile</p>
            </div>

            <!-- Status Message -->
            <div v-if="status" class="mb-4 p-4 rounded-lg bg-trello-green/10 border border-trello-green text-trello-green text-sm font-medium">
                ✓ {{ status }}
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-2 block w-full"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="seu@email.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div>
                    <InputLabel for="password" value="Senha" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-2 block w-full"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <label for="remember" class="ms-2 text-sm text-text-muted cursor-pointer font-medium">
                        Lembrar-me
                    </label>
                </div>

                <!-- Submit Button and Links -->
                <div class="flex flex-col gap-4">
                    <PrimaryButton
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                        class="w-full justify-center py-3 text-base font-bold"
                    >
                        {{ form.processing ? '⏳ Entrando...' : '🚀 Entrar' }}
                    </PrimaryButton>

                    <div class="flex items-center justify-between text-sm gap-2">
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-brand hover:opacity-80 transition font-semibold"
                        >
                            Esqueceu a senha?
                        </Link>
                        <Link
                            :href="route('register')"
                            class="text-brand hover:opacity-80 transition font-semibold"
                        >
                            Criar conta
                        </Link>
                    </div>
                </div>
            </form>

            <!-- Divider -->
            <div class="mt-8 relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-border-main"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-surface text-text-muted font-medium">ou</span>
                </div>
            </div>

            <!-- Info Card -->
            <div class="mt-8 p-4 rounded-lg border border-border-main bg-surface-variant">
                <p class="text-sm text-text-muted mb-2">
                    <strong class="text-text-main">Primeira vez aqui?</strong>
                </p>
                <p class="text-sm text-text-muted mb-3">
                    Crie uma conta para começar a gerenciar seus projetos com B-Agile.
                </p>
                <Link
                    :href="route('register')"
                    class="inline-block btn-secondary w-full text-center"
                >
                    Criar Conta Grátis
                </Link>
            </div>
        </div>
    </GuestLayout>
</template>
