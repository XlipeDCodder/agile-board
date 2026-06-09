<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const form = useForm({
    id: null,
    name: '',
    description: '',
    due_date: '',
    status: 'open',
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.status = 'open';
    showModal.value = true;
};

const openEditModal = (project) => {
    isEditing.value = true;
    form.id = project.id;
    form.name = project.name;
    form.description = project.description;
    form.due_date = project.due_date;
    form.status = project.status;
    showModal.value = true;
};

const toggleStatus = (project) => {
    const newStatus = project.status === 'open' ? 'completed' : 'open';
    form.id = project.id;
    form.name = project.name;
    form.description = project.description;
    form.due_date = project.due_date;
    form.status = newStatus;
    
    form.put(route('projects.update', project.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const saveProject = () => {
    if (isEditing.value) {
        form.put(route('projects.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('projects.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const [year, month, day] = dateString.split('-');
    return new Date(year, month - 1, day).toLocaleDateString('pt-BR');
};

const isOverdue = (dateString) => {
    if (!dateString) return false;
    const [year, month, day] = dateString.split('-');
    const due = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due < today;
};

const deleteProject = (id) => {
    if (confirm('Tem certeza que deseja excluir este projeto?')) {
        router.delete(route('projects.destroy', id));
    }
};
</script>

<template>
    <Head title="Projetos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-4xl text-text-main leading-tight inline-flex items-center gap-3"><Icon name="projects" :size="32" /> Projetos</h2>
                <button 
                    @click="openCreateModal" 
                    class="btn-primary flex items-center gap-2 shadow-lg hover:shadow-xl transition"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Novo Projeto
                </button>
            </div>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8">
            <!-- Empty State -->
            <div v-if="projects.length === 0" class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-text-muted mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-xl font-bold text-text-main mb-2">Nenhum projeto encontrado</h3>
                <p class="text-text-muted mb-8">Crie seu primeiro projeto para começar</p>
                <button 
                    @click="openCreateModal"
                    class="btn-primary"
                >
                    Criar Projeto
                </button>
            </div>

            <!-- Projects Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="project in projects" 
                    :key="project.id" 
                    class="card card-hover group flex flex-col h-full hover:border-brand/50 transition-all"
                    :class="{'opacity-60': project.status === 'completed'}"
                >
                    <!-- Header -->
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 
                                class="text-lg font-bold text-text-main group-hover:text-brand transition"
                                :class="{'line-through text-text-muted': project.status === 'completed'}"
                            >
                                {{ project.name }}
                            </h3>
                            <div v-if="project.status === 'completed'" class="mt-2">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-trello-green/10 text-trello-green">
                                    <Icon name="check" :size="13" /> Concluído
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="project.description" class="text-text-muted text-sm mb-4 flex-1 line-clamp-3">
                        {{ project.description }}
                    </p>
                    <p v-else class="text-text-muted text-sm italic mb-4 flex-1">
                        Sem descrição
                    </p>

                    <!-- Due Date -->
                    <div class="mb-4 p-3 rounded-lg bg-surface border border-border-main">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text-muted font-medium">📅 Vencimento:</span>
                            <span 
                                class="font-bold"
                                :class="{
                                    'text-trello-red': isOverdue(project.due_date) && project.status !== 'completed',
                                    'text-text-main': !isOverdue(project.due_date) || project.status === 'completed'
                                }"
                            >
                                {{ formatDate(project.due_date) }}
                            </span>
                        </div>
                    </div>

                    <!-- Footer with Actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-border-main">
                        <div class="text-sm text-text-muted font-medium">
                            <span class="font-bold text-text-main">{{ project.items_count }}</span> itens
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="toggleStatus(project)" 
                                class="p-2.5 rounded-lg hover:bg-surface-hover transition text-text-muted hover:text-trello-green hover:scale-110 active:scale-95"
                                :title="project.status === 'open' ? 'Marcar como concluído' : 'Reabrir projeto'"
                            >
                                <svg v-if="project.status === 'open'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                            <button 
                                @click="openEditModal(project)" 
                                class="p-2.5 rounded-lg hover:bg-surface-hover transition text-text-muted hover:text-brand hover:scale-110 active:scale-95"
                                title="Editar projeto"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button 
                                @click="deleteProject(project.id)" 
                                class="p-2.5 rounded-lg hover:bg-surface-hover transition text-text-muted hover:text-trello-red hover:scale-110 active:scale-95"
                                title="Excluir projeto"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para criar/editar projeto -->
        <Modal :show="showModal" @close="closeModal" max-width="md">
            <div class="p-6 bg-surface-variant">
                <h2 class="text-2xl font-bold mb-6 text-text-main inline-flex items-center gap-2">
                    <Icon :name="isEditing ? 'edit' : 'plus'" :size="22" />
                    {{ isEditing ? 'Editar Projeto' : 'Novo Projeto' }}
                </h2>

                <form @submit.prevent="saveProject" class="space-y-6">
                    <!-- Nome -->
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Nome do Projeto *</label>
                        <input 
                            type="text" 
                            v-model="form.name" 
                            class="input-field w-full"
                            placeholder="Ex: Website Redesign"
                            required
                        >
                    </div>

                    <!-- Descrição -->
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Descrição</label>
                        <textarea 
                            v-model="form.description" 
                            rows="4" 
                            class="input-field w-full"
                            placeholder="Descreva os objetivos do projeto..."
                        ></textarea>
                    </div>

                    <!-- Data de Vencimento -->
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Data de Vencimento</label>
                        <input 
                            type="date" 
                            v-model="form.due_date" 
                            class="input-field w-full"
                        >
                    </div>

                    <!-- Botões -->
                    <div class="flex justify-end gap-3 pt-6 border-t border-border-main">
                        <button 
                            type="button" 
                            @click="closeModal" 
                            class="btn-secondary"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="btn-primary"
                        >
                            {{ form.processing ? '⏳ Salvando...' : '💾 Salvar' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
