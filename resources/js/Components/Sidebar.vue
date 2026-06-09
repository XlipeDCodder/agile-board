<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';

const emit = defineEmits(['toggle']);
const isExpanded = ref(true);

const toggleSidebar = () => {
    isExpanded.value = !isExpanded.value;
    localStorage.setItem('sidebar-expanded', isExpanded.value);
    emit('toggle', isExpanded.value);
};

onMounted(() => {
    const saved = localStorage.getItem('sidebar-expanded');
    if (saved !== null) {
        isExpanded.value = saved === 'true';
        emit('toggle', isExpanded.value);
    }
});

const navItems = [
    { icon: 'dashboard', label: 'Dashboard', route: 'dashboard' },
    { icon: 'board', label: 'Quadro', route: 'board' },
    { icon: 'backlog', label: 'Backlog', route: 'backlog.index' },
    { icon: 'completed', label: 'Concluídos', route: 'completed.index' },
    { icon: 'projects', label: 'Projetos', route: 'projects.index' },
    { icon: 'time-entries', label: 'Apontamento', route: 'time-entries.index' },
    { icon: 'deploys', label: 'Deploys', route: 'deploys.index' },
];

const adminItems = [
    { icon: 'admin', label: 'Admin', route: 'admin.columns.index' },
    { icon: 'reports', label: 'Relatórios', route: 'admin.reports.index' },
    { icon: 'users', label: 'Usuários', route: 'admin.users.index' },
    { icon: 'bot', label: 'Bot Config', route: 'admin.bot-config.index', requiresConfirm: true },
];

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.is_admin);

const isActive = (route) => page.url.includes(route.split('.')[0]);

const showConfirmModal = ref(false);
const pendingRoute = ref(null);

const openConfirm = (item) => {
    pendingRoute.value = item.route;
    showConfirmModal.value = true;
};

const confirmNavigation = () => {
    const target = pendingRoute.value;
    showConfirmModal.value = false;
    pendingRoute.value = null;
    if (target) {
        router.visit(route(target));
    }
};

const cancelNavigation = () => {
    showConfirmModal.value = false;
    pendingRoute.value = null;
};
</script>

<template>
    <div :class="['sidebar', isExpanded ? 'expanded' : 'collapsed']">
        <!-- Logo Area -->
        <div class="flex items-center justify-between p-4 border-b border-border-main">
            <Link 
                href="/" 
                class="flex items-center gap-3 hover:opacity-80 transition"
                :class="{'justify-center': !isExpanded}"
            >
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand flex-shrink-0">
                    <span class="text-base font-bold text-white">B</span>
                </div>
                <span v-if="isExpanded" class="text-lg font-bold text-text-main">B-Agile</span>
            </Link>
        </div>

        <!-- Navigation Items -->
        <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
            <!-- Main Navigation -->
            <div>
                <div v-if="isExpanded" class="px-4 py-2 text-xs font-semibold text-text-muted uppercase tracking-wider">
                    Menu
                </div>
                
                <Link
                    v-for="item in navItems"
                    :key="item.route"
                    :href="route(item.route)"
                    :class="[
                        'sidebar-item group',
                        isActive(item.route) ? 'active' : ''
                    ]"
                >
                    <Icon :name="item.icon" :size="20" class="flex-shrink-0" />
                    <span v-if="isExpanded" class="text-sm font-medium">{{ item.label }}</span>
                    <div v-if="!isExpanded" class="absolute left-full ml-2 px-2 py-1 bg-surface-variant text-text-main text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-lg">
                        {{ item.label }}
                    </div>
                </Link>
            </div>

            <!-- Admin Section -->
            <div v-if="isAdmin">
                <div v-if="isExpanded" class="px-4 py-2 mt-4 text-xs font-semibold text-text-muted uppercase tracking-wider">
                    Admin
                </div>
                
                <template v-for="item in adminItems" :key="item.route">
                    <a
                        v-if="item.requiresConfirm"
                        href="#"
                        @click.prevent="openConfirm(item)"
                        :class="[
                            'sidebar-item group',
                            isActive(item.route) ? 'active' : ''
                        ]"
                    >
                        <Icon :name="item.icon" :size="20" class="flex-shrink-0" />
                        <span v-if="isExpanded" class="text-sm font-medium">{{ item.label }}</span>
                        <div v-if="!isExpanded" class="absolute left-full ml-2 px-2 py-1 bg-surface-variant text-text-main text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-lg">
                            {{ item.label }}
                        </div>
                    </a>
                    <Link
                        v-else
                        :href="route(item.route)"
                        :class="[
                            'sidebar-item group',
                            isActive(item.route) ? 'active' : ''
                        ]"
                    >
                        <Icon :name="item.icon" :size="20" class="flex-shrink-0" />
                        <span v-if="isExpanded" class="text-sm font-medium">{{ item.label }}</span>
                        <div v-if="!isExpanded" class="absolute left-full ml-2 px-2 py-1 bg-surface-variant text-text-main text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-lg">
                            {{ item.label }}
                        </div>
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Modal de confirmação para itens sensíveis -->
        <Modal :show="showConfirmModal" @close="cancelNavigation" max-width="md">
            <div class="p-6 bg-surface-variant">
                <div class="flex items-start gap-3 mb-4">
                    <div class="text-3xl">⚠️</div>
                    <div>
                        <h3 class="text-lg font-bold text-text-main">Configurações sensíveis</h3>
                        <p class="text-sm text-text-muted mt-1">
                            Você está prestes a acessar configurações sensíveis. Alterações incorretas podem desativar o assistente Icarus, expor a chave de API ou enviar dados a serviços externos. Prossiga com cautela.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-border-main">
                    <button type="button" @click="cancelNavigation" class="btn-secondary">Cancelar</button>
                    <button type="button" @click="confirmNavigation" class="btn-primary">Continuar</button>
                </div>
            </div>
        </Modal>

        <!-- Footer -->
        <div class="border-t border-border-main p-4 space-y-3">
            <!-- Theme Toggle -->
            <div class="flex justify-center">
                <ThemeToggle />
            </div>

            <!-- User Menu -->
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-surface-hover transition cursor-pointer group"
                 @click="$emit('open-user-menu')"
                 :class="{'justify-center': !isExpanded}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand text-white text-xs font-bold flex-shrink-0">
                    {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <div v-if="isExpanded" class="min-w-0">
                    <p class="text-sm font-medium text-text-main truncate">{{ page.props.auth.user.name }}</p>
                    <p class="text-xs text-text-muted truncate">{{ page.props.auth.user.email }}</p>
                </div>
                <div v-if="!isExpanded" class="absolute left-full ml-2 px-2 py-1 bg-surface-variant text-text-main text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-lg">
                    {{ page.props.auth.user.name }}
                </div>
            </div>
        </div>

        <!-- Toggle Button -->
        <button
            @click="toggleSidebar"
            class="absolute -right-3 top-1/2 transform -translate-y-1/2 bg-brand text-white rounded-full p-1.5 hover:opacity-90 transition shadow-lg z-50"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path 
                    stroke-linecap="round" 
                    stroke-linejoin="round" 
                    stroke-width="2" 
                    :d="isExpanded ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7'"
                />
            </svg>
        </button>
    </div>
</template>

<style scoped>
.sidebar {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-item.active {
    background-color: rgb(var(--color-brand) / 0.1);
}
</style>
