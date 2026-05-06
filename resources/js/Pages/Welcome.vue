<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-surface via-surface-variant to-surface text-text-main transition-colors duration-300">
        <!-- Navigation -->
        <nav class="sticky top-0 z-40 border-b border-border-main bg-surface-variant/80 backdrop-blur-md supports-[backdrop-filter]:bg-surface-variant/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <Link href="/" class="flex items-center space-x-3 hover:opacity-80 transition group">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand/80 shadow-lg group-hover:shadow-xl transition">
                            <span class="text-lg font-bold text-white">B</span>
                        </div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-brand to-brand/80 bg-clip-text text-transparent">B-Agile</span>
                    </Link>
                    
                    <div class="flex items-center gap-4">
                        <ThemeToggle />
                        <div class="flex gap-3">
                            <Link
                                :href="route('login')"
                                class="px-4 py-2 rounded-lg text-text-main hover:bg-surface-hover transition font-medium"
                            >
                                Entrar
                            </Link>
                            <Link
                                :href="route('register')"
                                class="btn-primary"
                            >
                                Registrar
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative overflow-hidden pt-20 pb-32 sm:pt-32 sm:pb-40">
            <!-- Background Elements -->
            <div class="absolute inset-0 -z-10 overflow-hidden">
                <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-brand/10 blur-3xl animate-pulse-subtle"></div>
                <div class="absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-brand/5 blur-3xl animate-pulse-subtle" style="animation-delay: 1s;"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <!-- Main Headline -->
                <div class="space-y-6 mb-12 animate-fade-in">
                    <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold leading-tight">
                        <span class="block text-text-main">Gerencie seus projetos</span>
                        <span class="block bg-gradient-to-r from-brand via-brand/80 to-brand bg-clip-text text-transparent">com simplicidade e poder</span>
                    </h1>
                    
                    <p class="text-xl text-text-muted max-w-2xl mx-auto leading-relaxed">
                        B-Agile é a ferramenta perfeita para equipes que querem organizar projetos, acompanhar demandas e colaborar em tempo real com um design moderno e intuitivo.
                    </p>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16 animate-slide-up">
                    <Link
                        :href="route('register')"
                        class="btn-primary px-8 py-4 text-lg shadow-lg hover:shadow-xl transition"
                    >
                        🚀 Começar Agora
                    </Link>
                    <a
                        href="#features"
                        class="btn-secondary px-8 py-4 text-lg"
                    >
                        ↓ Conhecer Mais
                    </a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 sm:py-32 border-t border-border-main">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl sm:text-5xl font-bold text-text-main mb-4">
                        Tudo que você precisa
                    </h2>
                    <p class="text-xl text-text-muted max-w-2xl mx-auto">
                        Recursos poderosos para organizar e colaborar com sua equipe
                    </p>
                </div>

                <!-- Feature Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="card card-hover group">
                        <div class="text-4xl mb-4">📋</div>
                        <h3 class="text-xl font-bold text-text-main mb-2 group-hover:text-brand transition">Quadro Kanban</h3>
                        <p class="text-text-muted">Visualize e organize suas tarefas em colunas. Arraste e solte para atualizar o status em tempo real.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="card card-hover group">
                        <div class="text-4xl mb-4">🎯</div>
                        <h3 class="text-xl font-bold text-text-main mb-2 group-hover:text-brand transition">Gerenciamento de Projetos</h3>
                        <p class="text-text-muted">Crie e organize projetos com datas de vencimento, descrições e acompanhamento de progresso.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="card card-hover group">
                        <div class="text-4xl mb-4">👥</div>
                        <h3 class="text-xl font-bold text-text-main mb-2 group-hover:text-brand transition">Colaboração em Tempo Real</h3>
                        <p class="text-text-muted">Atribua tarefas, deixe comentários e receba notificações instantâneas de mudanças.</p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="card card-hover group">
                        <div class="text-4xl mb-4">⏱️</div>
                        <h3 class="text-xl font-bold text-text-main mb-2 group-hover:text-brand transition">Apontamento de Tempo</h3>
                        <p class="text-text-muted">Registre o tempo gasto em cada tarefa e acompanhe a produtividade da equipe.</p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="card card-hover group">
                        <div class="text-4xl mb-4">🌙</div>
                        <h3 class="text-xl font-bold text-text-main mb-2 group-hover:text-brand transition">Dark Mode</h3>
                        <p class="text-text-muted">Alterne entre temas claro e escuro para uma experiência confortável em qualquer hora do dia.</p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="card card-hover group">
                        <div class="text-4xl mb-4">📱</div>
                        <h3 class="text-xl font-bold text-text-main mb-2 group-hover:text-brand transition">Responsivo</h3>
                        <p class="text-text-muted">Funciona perfeitamente em desktop, tablet e mobile. Trabalhe de qualquer lugar.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 sm:py-32 border-t border-border-main">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-4xl sm:text-5xl font-bold text-text-main mb-6">
                    Pronto para começar?
                </h2>
                <p class="text-xl text-text-muted mb-8">
                    Crie sua conta gratuitamente e comece a gerenciar seus projetos agora mesmo.
                </p>
                <Link
                    :href="route('register')"
                    class="btn-primary px-8 py-4 text-lg inline-block shadow-lg hover:shadow-xl transition"
                >
                    Criar Conta Grátis
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border-main bg-surface-variant/50 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <p class="text-text-muted text-sm">© 2024 B-Agile. Todos os direitos reservados.</p>
            </div>
        </footer>
    </div>
</template>
