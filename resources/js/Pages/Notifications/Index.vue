<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    notifications: Object, // paginator
});

const formatDate = (iso) => {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString('pt-BR'); }
    catch (e) { return ''; }
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

const markRead = (n) => {
    if (!n.read_at) {
        router.post(route('notifications.read', n.id), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }
    if (n.data?.url) {
        router.visit(n.data.url);
    }
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};

const unreadCount = computed(() => props.notifications.data.filter(n => !n.read_at).length);
</script>

<template>
    <Head title="Notificações" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-4xl text-text-main leading-tight">🔔 Notificações</h2>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <p class="text-sm text-text-muted">
                    {{ notifications.total }} notificações no total
                    <span v-if="unreadCount > 0">· <strong class="text-brand">{{ unreadCount }} não lidas nesta página</strong></span>
                </p>
                <button v-if="unreadCount > 0" @click="markAllRead" class="btn-secondary text-sm">
                    Marcar todas como lidas
                </button>
            </div>

            <div class="bg-surface-variant border border-border-main rounded-2xl shadow-sm overflow-hidden">
                <div v-if="notifications.data.length === 0" class="p-12 text-center text-text-muted">
                    <div class="text-5xl mb-3">🔕</div>
                    <p>Nenhuma notificação ainda.</p>
                </div>
                <button v-else v-for="n in notifications.data" :key="n.id"
                        @click="markRead(n)"
                        class="w-full text-left px-5 py-4 border-b border-border-main hover:bg-surface transition flex gap-4 items-start"
                        :class="{ 'bg-brand/5': !n.read_at }">
                    <div class="text-2xl flex-shrink-0">{{ iconFor(n.type) }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-text-main" :class="{ 'font-bold': !n.read_at }">
                            {{ n.message }}
                        </p>
                        <p class="text-xs text-text-muted mt-1">{{ formatDate(n.created_at) }}</p>
                        <div v-if="n.data?.rejection_reason" class="mt-2 text-xs text-trello-red bg-trello-red/10 border border-trello-red/30 rounded-lg px-3 py-2">
                            Motivo: {{ n.data.rejection_reason }}
                        </div>
                        <div v-if="n.data?.notes" class="mt-2 text-xs text-text-muted bg-surface border border-border-main rounded-lg px-3 py-2">
                            Notas: {{ n.data.notes }}
                        </div>
                    </div>
                    <span v-if="!n.read_at" class="w-2 h-2 mt-2 bg-brand rounded-full flex-shrink-0"></span>
                </button>
            </div>

            <!-- Paginação simples -->
            <div v-if="notifications.last_page > 1" class="flex justify-center gap-2">
                <Link v-for="link in notifications.links" :key="link.label"
                      :href="link.url || '#'"
                      v-html="link.label"
                      :class="[
                          'px-3 py-1 rounded text-sm',
                          link.active ? 'bg-brand text-white' : 'bg-surface-variant border border-border-main text-text-main hover:bg-surface',
                          !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                      ]" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
