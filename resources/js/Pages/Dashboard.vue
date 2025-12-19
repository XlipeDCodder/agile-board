<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PieChart from '@/Components/PieChart.vue';
import Modal from '@/Components/Modal.vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';

const props = defineProps({
    columns: Array,
    users: Array,
    idleUsers: Array,
    unassignedItems: Array,
    allUsers: Array,
});

const selectedUser = ref(null);
const showUserModal = ref(false);

const openUserModal = (user) => {
    selectedUser.value = user;
    showUserModal.value = true;
};

const closeUserModal = () => {
    showUserModal.value = false;
    selectedUser.value = null;
};

// Item Edit Modal Logic
const showEditItemModal = ref(false);
const editItemForm = useForm({
    id: null,
    title: '',
    description: '',
    assignee_ids: [],
    column_id: null, // Keep track of column for update
    priority: '',
    type: 'task', // Default valid value
    estimation: null,
    due_date: null,
});

const openEditItemModal = (item) => {
    editItemForm.id = item.id;
    editItemForm.title = item.title;
    editItemForm.description = item.description;
    editItemForm.assignee_ids = []; // Unassigned, so empty
    editItemForm.column_id = item.column_id;
    editItemForm.priority = item.priority;
    editItemForm.type = item.type;
    editItemForm.estimation = item.estimation;
    editItemForm.due_date = item.due_date;
    showEditItemModal.value = true;
};

const closeEditItemModal = () => {
    showEditItemModal.value = false;
    editItemForm.reset();
};

const saveItem = () => {
    editItemForm.put(route('items.update', editItemForm.id), {
        preserveScroll: true,
        onSuccess: () => closeEditItemModal(),
    });
};

const chartData = computed(() => {
    return {
        labels: props.columns.map(col => col.name),
        datasets: [
            {
                backgroundColor: ['#41B883', '#E46651', '#00D8FF', '#DD1B16', '#F7B911'],
                data: props.columns.map(col => col.items_count)
            }
        ]
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium text-text-primary mb-4" v-if="unassignedItems && unassignedItems.length > 0">Cards Sem Responsável</h3>
                        <div v-if="unassignedItems && unassignedItems.length > 0" class="flex overflow-x-auto space-x-4 pb-4 mb-4 border-b border-accent scrollbar-thin scrollbar-thumb-accent scrollbar-track-secondary">
                            <div 
                                v-for="item in unassignedItems" 
                                :key="item.id" 
                                @click="openEditItemModal(item)"
                                class="bg-primary border border-red-300 rounded-lg p-3 w-48 flex-shrink-0 cursor-pointer hover:shadow-md transition relative group"
                            >
                                <div class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center animate-pulse">!</div>
                                <h4 class="font-bold text-sm text-text-primary truncate mb-1">{{ item.title }}</h4>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="px-2 py-0.5 rounded bg-accent text-white">{{ item.column.name }}</span>
                                    <span class="text-text-secondary">{{ item.priority }}</span>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-lg font-medium text-text-primary mb-4">Workload</h3>
                        <div class="flex overflow-x-auto space-x-4 pb-4 mb-4 border-b border-accent scrollbar-thin scrollbar-thumb-accent scrollbar-track-secondary">
                            <div 
                                v-for="user in users" 
                                :key="user.id" 
                                @click="openUserModal(user)"
                                class="flex flex-col items-center cursor-pointer hover:opacity-80 transition flex-shrink-0"
                            >
                                <div class="h-12 w-12 rounded-full bg-primary flex items-center justify-center border-2 border-accent">
                                    <span class="text-lg font-bold text-text-primary">{{ user.name.charAt(0) }}</span>
                                </div>
                                <span class="text-xs text-text-secondary mt-1 whitespace-nowrap">{{ user.name.split(' ')[0] }}</span>
                                <span class="text-xs font-bold text-blue-500">{{ user.assigned_items.length }} cards</span>
                            </div>
                            <div v-if="users.length === 0" class="text-sm text-text-secondary w-full text-center">
                                Nenhum membro com tarefas ativas no momento.
                            </div>
                        </div>

                        <h3 class="text-lg font-medium text-text-primary mb-4">Membros Disponíveis</h3>
                        <div class="flex overflow-x-auto space-x-4 pb-4 mb-4 border-b border-accent scrollbar-thin scrollbar-thumb-accent scrollbar-track-secondary">
                            <div 
                                v-for="user in idleUsers" 
                                :key="user.id" 
                                class="flex flex-col items-center flex-shrink-0 opacity-75"
                            >
                                <div class="h-12 w-12 rounded-full bg-secondary flex items-center justify-center border-2 border-green-500">
                                    <span class="text-lg font-bold text-text-primary">{{ user.name.charAt(0) }}</span>
                                </div>
                                <span class="text-xs text-text-secondary mt-1 whitespace-nowrap">{{ user.name.split(' ')[0] }}</span>
                                <span class="text-xs font-bold text-green-500">Livre</span>
                            </div>
                            <div v-if="idleUsers.length === 0" class="text-sm text-text-secondary w-full text-center">
                                Todos os membros estão alocados.
                            </div>
                        </div>

                        <h3 class="text-lg font-medium text-text-primary mb-4">Distribuição de Itens por Coluna</h3>
                        <div class="h-64">
                            <PieChart :data="chartData" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showUserModal" @close="closeUserModal">
            <div class="p-6 bg-secondary text-text-primary" v-if="selectedUser">
                <div class="flex justify-between items-center mb-4 border-b border-accent pb-2">
                    <h2 class="text-2xl font-bold">{{ selectedUser.name }}</h2>
                    <span class="text-sm text-text-secondary">{{ selectedUser.assigned_items.length }} tarefas ativas</span>
                </div>
                
                <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-for="item in selectedUser.assigned_items" :key="item.id" class="bg-primary rounded-lg p-4 border border-accent shadow-sm">
                        <div class="flex justify-between items-start">
                            <h3 class="font-semibold text-lg">{{ item.title }}</h3>
                            <span class="px-2 py-1 text-xs rounded bg-accent text-white whitespace-nowrap">{{ item.column.name }}</span>
                        </div>
                        <p class="text-sm text-text-secondary mt-1 line-clamp-2" v-if="item.description">{{ item.description }}</p>
                        
                        <div v-if="item.subtasks && item.subtasks.length > 0" class="mt-3 pl-3 border-l-2 border-accent">
                            <p class="text-xs font-semibold text-text-secondary mb-1">Subtarefas</p>
                            <ul class="space-y-1">
                                <li v-for="subtask in item.subtasks" :key="subtask.id" class="text-sm flex items-center">
                                    <svg class="h-3 w-3 mr-2" :class="subtask.completed_at ? 'text-green-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" v-if="subtask.completed_at" />
                                        <circle cx="12" cy="12" r="10" stroke-width="2" v-else />
                                    </svg>
                                    <span :class="{'line-through text-text-secondary': subtask.completed_at}">{{ subtask.title }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button @click="closeUserModal" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Fechar</button>
                </div>
            </div>
        </Modal>

        <Modal :show="showEditItemModal" @close="closeEditItemModal">
            <div class="p-6 bg-secondary text-text-primary">
                <h2 class="text-2xl font-bold mb-4">Atribuir Responsável</h2>
                <form @submit.prevent="saveItem">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Título</label>
                            <input type="text" v-model="editItemForm.title" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm" disabled>
                        </div>
                         <div>
                            <label class="block text-sm font-medium">Descrição</label>
                            <textarea v-model="editItemForm.description" rows="3" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm" disabled></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-red-500 font-bold">Atribuir a:</label>
                            <Multiselect
                                v-model="editItemForm.assignee_ids"
                                :options="allUsers.map(user => user.id)"
                                :custom-label="opt => allUsers.find(user => user.id === opt).name"
                                :multiple="true"
                                placeholder="Selecione um responsável"
                                selectLabel="Clique para selecionar"
                                deselectLabel="Clique para remover"
                                selectedLabel="Selecionado"
                                class="mt-1"
                            ></Multiselect>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="button" @click="closeEditItemModal" class="px-4 py-2 bg-accent text-primary rounded-md">Cancelar</button>
                        <button type="submit" :disabled="editItemForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Salvar Atribuição</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
