<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
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
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (project) => {
    isEditing.value = true;
    form.id = project.id;
    form.name = project.name;
    form.description = project.description;
    form.due_date = project.due_date;
    showModal.value = true;
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
    return new Date(year, month - 1, day).toLocaleDateString();
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
                <h2 class="font-semibold text-xl text-text-primary leading-tight">Projetos</h2>
                <button @click="openCreateModal" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                    Novo Projeto
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="project in projects" :key="project.id" class="bg-secondary overflow-hidden shadow-sm rounded-lg border border-accent">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-lg font-bold text-text-primary">{{ project.name }}</h3>
                                <div class="flex space-x-2">
                                    <button @click="openEditModal(project)" class="text-blue-500 hover:text-blue-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="deleteProject(project.id)" class="text-red-500 hover:text-red-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <p class="text-text-secondary text-sm mb-4">{{ project.description || 'Sem descrição.' }}</p>
                            
                            <div class="flex justify-between items-center text-xs text-text-secondary border-t border-accent pt-4">
                                <div>
                                    <span class="font-bold">Vencimento:</span>
                                    <span :class="{'text-red-500 font-bold': isOverdue(project.due_date), 'ml-1': true}">
                                        {{ formatDate(project.due_date) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="font-bold">Itens:</span> {{ project.items_count }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="projects.length === 0" class="text-center text-text-secondary mt-10">
                    Nenhum projeto encontrado.
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <div class="p-6 bg-secondary text-text-primary">
                <h2 class="text-2xl font-bold mb-4">{{ isEditing ? 'Editar Projeto' : 'Novo Projeto' }}</h2>
                <form @submit.prevent="saveProject">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Nome</label>
                            <input type="text" v-model="form.name" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Descrição</label>
                            <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Data de Vencimento</label>
                            <input type="date" v-model="form.due_date" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="button" @click="closeModal" class="px-4 py-2 bg-accent text-primary rounded-md">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Salvar</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
