<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Icon from '@/Components/Icon.vue';

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

// Mapa tipo → {nome do ícone Lucide, cor}. Usado no dropdown e na página.
const iconFor = (type) => {
    switch (type) {
        case 'deployment_requested': return { name: 'deploys', color: 'text-amber-500' };
        case 'deployment_approved': return { name: 'check', color: 'text-emerald-500' };
        case 'deployment_rejected': return { name: 'circle-x', color: 'text-trello-red' };
        case 'production_deploy_completed': return { name: 'party', color: 'text-brand' };
        default: return { name: 'bell', color: 'text-text-muted' };
    }
};

// Listener Echo pra real-time. Quando uma Notification implementa
// ShouldBroadcast (ou tem 'broadcast' no via()), o Laravel envia o evento
// 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' no
// canal privado 'App.Models.User.{id}'. Quando chegar, incrementamos
// a contagem e prepend na lista do dropdown — sem precisar refetch.
let echoChannel = null;
const setupEchoListener = (userId) => {
    if (!window.Echo) return;
    echoChannel = window.Echo.private(`App.Models.User.${userId}`);
    echoChannel.notification((data) => {
        unreadCount.value = (unreadCount.value || 0) + 1;
        // Prepend uma representação leve no dropdown (ID temporário até refetch).
        notifications.value.unshift({
            id: data.id || `temp-${Date.now()}`,
            type: data.type || 'unknown',
            message: data.message || '(nova notificação)',
            data: data,
            read_at: null,
            created_at: new Date().toISOString(),
        });
        if (notifications.value.length > 8) notifications.value.pop();
    });
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    const userId = page.props.auth?.user?.id;
    if (userId) setupEchoListener(userId);
    // Polling reduzido pra 5 min como fallback caso o WebSocket caia —
    // o real-time via Echo é o caminho primário agora.
    pollTimer = setInterval(fetchDropdown, 300000);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (pollTimer) clearInterval(pollTimer);
    if (echoChannel) {
        try { echoChannel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated'); }
        catch (e) { /* ignore */ }
    }
});
</script>

<template>
    <div class="relative" data-notification-bell>
        <button @click="toggleDropdown"
                type="button"
                class="relative inline-flex items-center rounded-lg border border-border-main bg-surface-variant px-3 py-2 text-sm text-text-main transition hover:border-brand hover:bg-surface focus:outline-none"
                :title="`${unreadCount} notificação(ões) não lida(s)`">
            <Icon name="bell" :size="20" />
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
                    <div class="flex-shrink-0 mt-0.5" :class="iconFor(n.type).color">
                        <Icon :name="iconFor(n.type).name" :size="18" />
                    </div>
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
