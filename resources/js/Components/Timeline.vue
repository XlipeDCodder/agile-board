<script setup>
import { computed } from 'vue';

const props = defineProps({
    events: { type: Array, required: true },
});

const eventStyles = {
    project_created: { dot: 'bg-emerald-500', ring: 'ring-emerald-500/20' },
    project_completed: { dot: 'bg-brand', ring: 'ring-brand/20' },
    item_created: { dot: 'bg-blue-500', ring: 'ring-blue-500/20' },
    item_completed: { dot: 'bg-emerald-500', ring: 'ring-emerald-500/20' },
    item_moved: { dot: 'bg-indigo-500', ring: 'ring-indigo-500/20' },
    item_assigned: { dot: 'bg-purple-500', ring: 'ring-purple-500/20' },
    comment: { dot: 'bg-slate-400', ring: 'ring-slate-400/20' },
    time_logged: { dot: 'bg-amber-500', ring: 'ring-amber-500/20' },
    user_joined: { dot: 'bg-brand', ring: 'ring-brand/20' },
};

const formatDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const styleFor = (type) => eventStyles[type] || { dot: 'bg-slate-500', ring: 'ring-slate-500/20' };

const hasEvents = computed(() => props.events && props.events.length > 0);
</script>

<template>
    <div v-if="!hasEvents" class="text-center py-12 text-text-muted">
        Nenhum evento para exibir.
    </div>
    <div v-else class="relative">
        <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-border-main"></div>
        <ul class="space-y-5">
            <li v-for="(event, idx) in events" :key="idx" class="relative pl-16">
                <span
                    :class="[
                        'absolute left-4 top-2 h-5 w-5 rounded-full ring-4 flex items-center justify-center text-[10px]',
                        styleFor(event.type).dot,
                        styleFor(event.type).ring,
                    ]"
                ></span>
                <div class="bg-surface border border-border-main rounded-xl p-4 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <div class="flex items-center gap-2 text-text-main font-bold">
                            <span class="text-lg">{{ event.icon }}</span>
                            <span>{{ event.title }}</span>
                        </div>
                        <span class="text-xs text-text-muted whitespace-nowrap">{{ formatDate(event.date) }}</span>
                    </div>
                    <p v-if="event.description" class="text-sm text-text-muted mt-1 whitespace-pre-wrap">{{ event.description }}</p>
                    <p v-if="event.actor" class="text-xs text-text-muted mt-2">
                        Por <span class="font-semibold text-text-main">{{ event.actor }}</span>
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
