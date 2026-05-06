<script setup>
import { ref, onMounted } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const notificationMessage = ref(null);
const showNotification = ref(false);
const showUserMenu = ref(false);
const isSidebarExpanded = ref(true);

const handleSidebarToggle = (expanded) => {
    isSidebarExpanded.value = expanded;
};

const playNotificationSound = () => {
    const audio = new Audio('/notify.mp3');
    audio.play().catch(error => console.log('Audio play failed:', error));
};

onMounted(() => {
    const page = usePage();
    if (page.props.auth.user && window.Echo) {
        window.Echo.private(`App.Models.User.${page.props.auth.user.id}`)
            .listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (notification) => {
                const isAssignment = 
                    notification.type === 'App\\Notifications\\ItemAssignedNotification' || 
                    notification.data?.type === 'assignment' ||
                    notification.type === 'assignment';

                if (isAssignment) {
                    notificationMessage.value = notification.message || notification.data?.message || 'Nova atribuição recebida';
                    showNotification.value = true;
                    playNotificationSound();
                    
                    setTimeout(() => {
                        showNotification.value = false;
                    }, 5000);
                }
            });
    }
});
</script>

<template>
    <div class="min-h-screen bg-surface text-text-main transition-colors duration-300 flex overflow-hidden">
        <!-- Sidebar -->
        <Sidebar @toggle="handleSidebarToggle" @open-user-menu="showUserMenu = true" />

        <!-- Main Content Wrapper -->
        <div 
            class="flex-1 flex flex-col min-w-0 transition-all duration-300" 
            :class="isSidebarExpanded ? 'ml-sidebar-width' : 'ml-sidebar-collapsed'"
        >
            <!-- Top Header -->
            <header class="sticky top-0 z-30 border-b border-border-main bg-surface-variant shadow-sm">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-text-main">{{ $page.props.title || 'B-Agile' }}</h1>
                    </div>

                    <!-- User Menu (Desktop) -->
                    <div class="hidden sm:flex items-center">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-border-main bg-surface-variant px-3 py-2 text-sm font-medium text-text-main transition hover:border-brand hover:bg-surface focus:outline-none"
                                >
                                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ $page.props.auth.user.name }}

                                    <svg
                                        class="-me-0.5 ms-2 h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Perfil
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Sair
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <!-- Page Heading -->
            <header class="border-b border-border-main bg-surface-variant shadow-sm" v-if="$slots.header">
                <div class="px-6 py-6">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-auto animate-fade-in">
                <slot />
            </main>
        </div>

        <!-- Notification Toast -->
        <transition
            enter-active-class="animate-slide-up"
            leave-active-class="animate-fade-in"
        >
            <div
                v-if="showNotification"
                class="fixed bottom-4 right-4 bg-surface-variant border border-border-main shadow-lg rounded-xl p-4 flex items-center space-x-3 z-50 max-w-sm"
            >
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-trello-green" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-text-main">Nova Atribuição</p>
                    <p class="text-sm text-text-muted">{{ notificationMessage }}</p>
                </div>
                <button @click="showNotification = false" class="text-text-muted hover:text-text-main">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.ml-sidebar-width {
    margin-left: 280px;
}
.ml-sidebar-collapsed {
    margin-left: 80px;
}

@media (max-width: 768px) {
    .ml-sidebar-width, .ml-sidebar-collapsed {
        margin-left: 0;
    }
}
</style>
