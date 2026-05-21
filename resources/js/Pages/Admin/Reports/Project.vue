<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Timeline from '@/Components/Timeline.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    project: Object,
    stats: Object,
    projects: Array,
    events: Array,
});

const selectedProject = ref(props.project.id);

const onChangeProject = () => {
    if (selectedProject.value && selectedProject.value !== props.project.id) {
        router.visit(route('admin.reports.project', selectedProject.value));
    }
};

const statusLabel = {
    open: 'Em andamento',
    completed: 'Concluído',
};

const formatDate = (dateString) => {
    if (!dateString) return '—';
    const [year, month, day] = String(dateString).split('-');
    if (!year || !month || !day) return dateString;
    return new Date(year, month - 1, parseInt(day, 10)).toLocaleDateString('pt-BR');
};
</script>

<template>
    <Head :title="`Relatório · ${project.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.reports.index')" class="text-text-muted hover:text-text-main">←</Link>
                <h2 class="font-bold text-4xl text-text-main leading-tight">📁 {{ project.name }}</h2>
            </div>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-6">
            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
                    <select v-model="selectedProject" @change="onChangeProject" class="input-field md:flex-1">
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <span class="px-3 py-1 rounded-full text-xs font-bold"
                        :class="project.status === 'completed' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-brand/10 text-brand'">
                        {{ statusLabel[project.status] || project.status }}
                    </span>
                </div>

                <p v-if="project.description" class="text-sm text-text-muted">{{ project.description }}</p>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Total de cards</div>
                        <div class="text-2xl font-bold text-text-main">{{ stats.items_total }}</div>
                    </div>
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Concluídos</div>
                        <div class="text-2xl font-bold text-emerald-500">{{ stats.items_completed }}</div>
                    </div>
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Horas apontadas</div>
                        <div class="text-2xl font-bold text-text-main">{{ stats.hours_logged }}h</div>
                    </div>
                    <div class="bg-surface rounded-xl p-4 border border-border-main">
                        <div class="text-xs text-text-muted">Prazo</div>
                        <div class="text-2xl font-bold text-text-main">{{ formatDate(project.due_date) }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm">
                <h3 class="text-lg font-bold text-text-main mb-4">Linha do tempo</h3>
                <Timeline :events="events" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
