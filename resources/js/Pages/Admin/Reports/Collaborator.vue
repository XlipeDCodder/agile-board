<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import Timeline from '@/Components/Timeline.vue';
import IcarusChat from '@/Components/IcarusChat.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    collaborator: Object,
    stats: Object,
    users: Array,
    events: Array,
});

const selectedUser = ref(props.collaborator.id);

const onChangeUser = () => {
    if (selectedUser.value && selectedUser.value !== props.collaborator.id) {
        router.visit(route('admin.reports.collaborator', selectedUser.value));
    }
};
</script>

<template>
    <Head :title="`Relatório · ${collaborator.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.reports.index')" class="text-text-muted hover:text-text-main">←</Link>
                <h2 class="font-bold text-4xl text-text-main leading-tight inline-flex items-center gap-3"><Icon name="user-circle" :size="32" /> {{ collaborator.name }}</h2>
            </div>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-6">
            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
                    <div class="h-14 w-14 rounded-full bg-brand text-white text-xl font-bold flex items-center justify-center">
                        {{ collaborator.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1">
                        <div class="font-bold text-text-main">{{ collaborator.name }}</div>
                        <div class="text-sm text-text-muted">{{ collaborator.email }}</div>
                    </div>
                    <select v-model="selectedUser" @change="onChangeUser" class="input-field md:w-64">
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Cards criados</div>
                        <div class="text-2xl font-bold text-text-main">{{ stats.items_created }}</div>
                    </div>
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Atribuídos ativos</div>
                        <div class="text-2xl font-bold text-brand">{{ stats.items_assigned_active }}</div>
                    </div>
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Horas apontadas</div>
                        <div class="text-2xl font-bold text-text-main">{{ stats.hours_logged }}h</div>
                    </div>
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Projetos envolvidos</div>
                        <div class="text-2xl font-bold text-text-main">{{ stats.projects_count }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm">
                <h3 class="text-lg font-bold text-text-main mb-4">Linha do tempo</h3>
                <Timeline :events="events" />
            </div>
        </div>

        <IcarusChat :user-id="collaborator.id" :user-name="collaborator.name" />
    </AuthenticatedLayout>
</template>
