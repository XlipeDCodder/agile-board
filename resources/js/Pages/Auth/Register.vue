<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registrar - B-Agile" />

        <div class="w-full animate-fade-in">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand/80 shadow-lg">
                        <span class="text-xl font-bold text-white">B</span>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-text-main">Criar Conta</h1>
                <p class="mt-2 text-text-muted">Comece a gerenciar seus projetos agora</p>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name Field -->
                <div>
                    <InputLabel for="name" value="Nome Completo" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-2 block w-full"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Seu nome completo"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <!-- Email Field -->
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-2 block w-full"
                        v-model="form.email"
                        required
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
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <InputLabel for="password_confirmation" value="Confirmar Senha" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-2 block w-full"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <!-- Submit Button and Link -->
                <div class="flex flex-col gap-4">
                    <PrimaryButton
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                        class="w-full justify-center py-3 text-base font-bold"
                    >
                        {{ form.processing ? '⏳ Criando conta...' : '🚀 Registrar' }}
                    </PrimaryButton>

                    <div class="text-center">
                        <span class="text-sm text-text-muted">Já tem uma conta? </span>
                        <Link
                            :href="route('login')"
                            class="text-brand hover:opacity-80 transition font-semibold"
                        >
                            Fazer login
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

            <!-- Terms Info -->
            <div class="mt-8 p-4 rounded-lg border border-border-main bg-surface-variant">
                <p class="text-xs text-text-muted text-center leading-relaxed">
                    Ao se registrar, você concorda com nossos <strong class="text-text-main">Termos de Serviço</strong> e <strong class="text-text-main">Política de Privacidade</strong>.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
