<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue'; 
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    items: Object, 
    users: Array,
});

const showItemModal = ref(false);

const itemForm = useForm({
    id: null, title: '', description: '', type: 'task',
    priority: 'Média', assignee_id: null, due_date: null,
    column_id: null, estimation: null, subtasks: [],
});

const openEditItemModal = (item) => {
    itemForm.id = item.id;
    itemForm.title = item.title;
    itemForm.description = item.description;
    itemForm.type = item.type;
    itemForm.priority = item.priority;
    itemForm.assignee_id = item.assignee_id;
    itemForm.due_date = item.due_date;
    itemForm.column_id = item.column_id;
    itemForm.estimation = item.estimation;
    // Precisamos garantir que as subtarefas existam no item para evitar erros
    itemForm.subtasks = item.subtasks || [];
    showItemModal.value = true;
};

const closeModal = () => { showItemModal.value = false; };

const saveItem = () => {
    itemForm.put(route('items.update', itemForm.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Backlog" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Backlog de Tarefas</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsável</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridade</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items.data" :key="item.id" @click="openEditItemModal(item)" class="hover:bg-gray-50 cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{{ item.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.title }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.column.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.assignee ? item.assignee.name : 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.priority }}</td>
                                </tr>
                                <tr v-if="items.data.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Nenhum item no backlog.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Links de Paginação -->
                    <div v-if="items.links.length > 3" class="p-6 bg-white border-t border-gray-200">
                        <Pagination :links="items.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Reutiliza o mesmo Modal que já temos -->
        <Modal :show="showItemModal" @close="closeModal">
             <div class="p-6">
                <h2 class="text-2xl font-bold mb-4">Editar Item</h2>
                <form @submit.prevent="saveItem">
                    <!-- O formulário de edição é o mesmo do quadro, mas sem a opção de criar subtarefas por enquanto -->
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2"><label>Título</label><input type="text" v-model="itemForm.title" class="mt-1 block w-full rounded-md"><div v-if="itemForm.errors.title" class="text-red-500 text-xs">{{ itemForm.errors.title }}</div></div>
                        <div class="md:col-span-2"><label>Descrição</label><textarea v-model="itemForm.description" rows="3" class="mt-1 block w-full rounded-md"></textarea></div>
                        <div><label>Responsável</label><select v-model="itemForm.assignee_id" class="mt-1 block w-full rounded-md"><option :value="null">Não atribuído</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
                        <div><label>Prioridade</label><select v-model="itemForm.priority" class="mt-1 block w-full rounded-md"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                        <div class="md:col-span-2"><label>Estimativa</label><select v-model="itemForm.estimation" class="mt-1 block w-full rounded-md"><option :value="null">Não estimado</option><option v-for="p in [1,2,3,5,8,13,20]" :value="p">{{p}} Pontos</option></select></div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="closeModal" class="px-4 py-2 bg-gray-200 rounded-md">Cancelar</button><button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Salvar</button></div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
