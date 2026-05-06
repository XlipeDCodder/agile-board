<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
</script>

<template>
    <div class="min-h-screen bg-surface text-text-main transition-colors duration-300 flex flex-col">
        <!-- Header with Theme Toggle -->
        <div class="border-b border-border-main bg-surface-variant/95 backdrop-blur supports-[backdrop-filter]:bg-surface-variant/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <Link href="/" class="flex items-center space-x-2 hover:opacity-80 transition">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand">
                            <span class="text-sm font-bold text-white">B</span>
                        </div>
                        <span class="text-lg font-bold text-text-main">B-Agile</span>
                    </Link>
                    <ThemeToggle />
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <!-- Gradient Background -->
            <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none">
                <div class="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-brand/5 blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-trello-green/5 blur-3xl"></div>
            </div>

            <!-- Form Container -->
            <div class="w-full max-w-md">
                <div class="bg-surface-variant rounded-xl shadow-lg border border-border-main overflow-hidden animate-fade-in">
                    <div class="px-6 py-8 sm:px-8">
                        <slot />
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-border-main bg-surface-variant/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <p class="text-center text-sm text-text-muted">
                    © 2024 B-Agile. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </div>
</template>
