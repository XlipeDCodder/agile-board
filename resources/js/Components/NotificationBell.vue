<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();

const isOpen = ref(false);
const notifications = ref([]);
// Inicia com o valor compartilhado pelo Inertia (HandleInertiaRequests).
// Polling atualiza depois.
const unreadCount = ref(page.props.notifications_unread_count ?? 0);
const loading = ref(false);

let pollTimer = null;

const fetchDropdown = async () => {
    try {
        const { data } = await axios.get(route('notifications.dropdown'));
        notifications.value = data.notifications;
        unreadCount.value = data.unread_count;
    } catch (e) {
        // silencioso — sino é secundário, não polui o user com erro
    }
};

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        loading.value = true;
        await fetchDropdown();
        loading.value = false;
    }
};

const closeDropdown = () => { isOpen.value = false; };

const handleClickOutside = (e) => {
    if (!e.target.closest('[data-notification-bell]')) {
        isOpen.value = false;
    }
};

const markRead = async (notification) => {
    if (!notification.read_at) {
        try {
            await axios.post(route('notifications.read', notification.id));
            notification.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch (e) { /* silent */ }
    }
    // Navega pra URL do contexto se houver.
    const url = notification.data?.url;
    if (url) {
        router.visit(url);
        closeDropdown();
    }
};

const markAllRead = async () => {
    try {
        await axios.post(route('notifications.read-all'));
        notifications.value.forEach(n => n.read_at = new Date().toISOString());
        unreadCount.value = 0;
    } catch (e) { /* silent */ }
};

const formatRelative = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    const diffMs = Date.now() - d.getTime();
    const min = Math.floor(diffMs / 60000);
    if (min < 1) return 'agora';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h}h`;
    const days = Math.floor(h / 24);
    if (days < 7) return `${days}d`;
    return d.toLocaleDateString('pt-BR');
};

const iconFor = (type) => {
    switch (type) {
        case 'deployment_requested': return '🚀';
        case 'deployment_approved': return '✅';
        case 'deployment_rejected': return '❌';
        case 'production_deploy_completed': return '🎉';
        default: return '🔔';
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    // Polling de 60s pra atualizar a contagem mesmo sem abrir o dropdown.
    pollTimer = setInterval(fetchDropdown, 60000);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <div class="relative" data-notification-bell>
        <button @click="toggleDropdown"
                type="button"
                class="relative inline-flex items-center rounded-lg border border-border-main bg-surface-variant px-3 py-2 text-sm text-text-main transition hover:border-brand hover:bg-surface focus:outline-none"
                :title="`${unreadCount} notificação(ões) não lida(s)`">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span v-if="unreadCount > 0"
                  class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold leading-none text-white bg-trello-red rounded-full">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div v-if="isOpen"
             class="absolute right-0 mt-2 w-96 max-h-[600px] bg-surface-variant border border-border-main rounded-2xl shadow-2xl z-50 flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-border-main">
                <h3 class="font-bold text-text-main text-sm">🔔 Notificações</h3>
                <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs text-brand hover:underline">
                    Marcar todas como lidas
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div v-if="loading" class="p-6 text-center text-sm text-text-muted">Carregando…</div>
                <div v-else-if="notifications.length === 0" class="p-6 text-center text-sm text-text-muted">
                    Nenhuma notificação ainda.
                </div>
                <button v-else v-for="n in notifications" :key="n.id"
                        @click="markRead(n)"
                        class="w-full text-left px-4 py-3 border-b border-border-main hover:bg-surface transition flex gap-3"
                        :class="{ 'bg-brand/5': !n.read_at }">
                    <div class="text-xl flex-shrink-0">{{ iconFor(n.type) }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-text-main" :class="{ 'font-bold': !n.read_at }">
                            {{ n.message }}
                        </p>
                        <p class="text-xs text-text-muted mt-0.5">{{ formatRelative(n.created_at) }}</p>
                    </div>
                    <span v-if="!n.read_at" class="w-2 h-2 mt-2 bg-brand rounded-full flex-shrink-0"></span>
                </button>
            </div>

            <div class="border-t border-border-main px-4 py-2">
                <Link :href="route('notifications.index')"
                      @click="closeDropdown"
                      class="text-xs text-brand hover:underline">
                    Ver todas as notificações →
                </Link>
            </div>
        </div>
    </div>
</template>
