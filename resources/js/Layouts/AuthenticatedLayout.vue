<script setup>
import { ref, onMounted } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import Icon from '@/Components/Icon.vue';
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
                    <div class="hidden sm:flex items-center gap-3">
                        <NotificationBell />
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-border-main bg-surface-variant px-3 py-2 text-sm font-medium text-text-main transition hover:border-brand hover:bg-surface focus:outline-none"
                                >
                                    <Icon name="user" :size="18" class="mr-2" />
                                    {{ $page.props.auth.user.name }}
                                    <Icon name="chevron" :size="16" class="-me-0.5 ms-2" />
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                    <Icon name="user" :size="16" />
                                    Perfil
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="flex items-center gap-2"
                                >
                                    <Icon name="logout" :size="16" />
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
                <div class="flex-shrink-0 text-trello-green">
                    <Icon name="bell" :size="24" />
                </div>
                <div>
                    <p class="text-sm font-medium text-text-main">Nova Atribuição</p>
                    <p class="text-sm text-text-muted">{{ notificationMessage }}</p>
                </div>
                <button @click="showNotification = false" class="text-text-muted hover:text-text-main">
                    <Icon name="x" :size="20" />
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
