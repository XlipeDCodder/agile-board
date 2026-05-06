<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

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
    { icon: '📊', label: 'Dashboard', route: 'dashboard', icon_svg: 'M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-4m0 0l4 4m-4-4v4' },
    { icon: '📋', label: 'Quadro', route: 'board', icon_svg: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { icon: '📚', label: 'Backlog', route: 'backlog.index', icon_svg: 'M4 6h16M4 12h16M4 18h16' },
    { icon: '✅', label: 'Concluídos', route: 'completed.index', icon_svg: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { icon: '📁', label: 'Projetos', route: 'projects.index', icon_svg: 'M5 13l4 4L19 7' },
    { icon: '⏱️', label: 'Apontamento', route: 'time-entries.index', icon_svg: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const adminItems = [
    { icon: '⚙️', label: 'Admin', route: 'admin.columns.index', icon_svg: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
];

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.is_admin);

const isActive = (route) => page.url.includes(route.split('.')[0]);
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
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon_svg" />
                    </svg>
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
                
                <Link
                    v-for="item in adminItems"
                    :key="item.route"
                    :href="route(item.route)"
                    :class="[
                        'sidebar-item group',
                        isActive(item.route) ? 'active' : ''
                    ]"
                >
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon_svg" />
                    </svg>
                    <span v-if="isExpanded" class="text-sm font-medium">{{ item.label }}</span>
                    <div v-if="!isExpanded" class="absolute left-full ml-2 px-2 py-1 bg-surface-variant text-text-main text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-lg">
                        {{ item.label }}
                    </div>
                </Link>
            </div>
        </nav>

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
