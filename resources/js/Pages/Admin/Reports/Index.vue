<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    projects: Array,
    users: Array,
});

const tab = ref('project');
const selectedProject = ref(null);
const selectedUser = ref(null);

const goProject = () => {
    if (!selectedProject.value) return;
    router.visit(route('admin.reports.project', selectedProject.value));
};

const goCollaborator = () => {
    if (!selectedUser.value) return;
    router.visit(route('admin.reports.collaborator', selectedUser.value));
};
</script>

<template>
    <Head title="Relatórios" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-4xl text-text-main leading-tight inline-flex items-center gap-3"><Icon name="reports" :size="32" /> Relatórios</h2>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm">
                <p class="text-text-muted mb-6">
                    Selecione uma visão para começar a análise.
                </p>

                <div class="flex gap-2 mb-6 border-b border-border-main">
                    <button
                        @click="tab = 'project'"
                        :class="['px-4 py-2 font-medium border-b-2 transition', tab === 'project' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']"
                    >
                        <span class="inline-flex items-center gap-1.5"><Icon name="projects" :size="16" /> Por Projeto</span>
                    </button>
                    <button
                        @click="tab = 'collaborator'"
                        :class="['px-4 py-2 font-medium border-b-2 transition', tab === 'collaborator' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']"
                    >
                        <span class="inline-flex items-center gap-1.5"><Icon name="user" :size="16" /> Por Colaborador</span>
                    </button>
                </div>

                <div v-if="tab === 'project'" class="space-y-4">
                    <label class="block text-sm font-bold text-text-main">Escolha um projeto</label>
                    <select v-model="selectedProject" class="input-field w-full">
                        <option :value="null">— Selecione —</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }} {{ p.status === 'completed' ? '(concluído)' : '' }}
                        </option>
                    </select>
                    <button
                        @click="goProject"
                        :disabled="!selectedProject"
                        class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Ver timeline do projeto
                    </button>
                </div>

                <div v-if="tab === 'collaborator'" class="space-y-4">
                    <label class="block text-sm font-bold text-text-main">Escolha um colaborador</label>
                    <select v-model="selectedUser" class="input-field w-full">
                        <option :value="null">— Selecione —</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }} · {{ u.email }}
                        </option>
                    </select>
                    <button
                        @click="goCollaborator"
                        :disabled="!selectedUser"
                        class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Ver timeline do colaborador
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
