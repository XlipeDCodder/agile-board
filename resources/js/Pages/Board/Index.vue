<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    columns: Array,
    users: Array,
});

const boardColumns = ref([]);
const showItemModal = ref(false);

const itemForm = useForm({
    id: null, title: '', description: '', type: 'task',
    priority: 'Média', assignee_id: null, due_date: null,
    column_id: null, estimation: null, subtasks: [],
});

const newSubtaskForm = useForm({
    title: '',
    parent_id: null,
});

onMounted(() => { boardColumns.value = props.columns; });

watch(() => props.columns, (newColumns) => {
    boardColumns.value = newColumns;
    if (showItemModal.value && itemForm.id) {
        let updatedItem = null;
        for (const column of newColumns) {
            const foundItem = column.items.find(item => item.id === itemForm.id);
            if (foundItem) {
                updatedItem = foundItem;
                break;
            }
        }
        if (updatedItem) {
            itemForm.subtasks = updatedItem.subtasks;
        }
    }
}, { deep: true });

function onDragEnd() {
    const reorderedData = boardColumns.value.map(c => ({ id: c.id, items: c.items.map(i => i.id) }));
    router.patch(route('board.reorder'), { columns: reorderedData }, { preserveScroll: true });
}

// AQUI ESTÁ A CORREÇÃO CRUCIAL
const openCreateItemModal = (columnId) => {

    itemForm.id = null;
    itemForm.title = '';
    itemForm.description = '';
    itemForm.type = 'task';
    itemForm.priority = 'Média';
    itemForm.assignee_id = null;
    itemForm.due_date = null;
    itemForm.estimation = null;
    itemForm.subtasks = [];
    itemForm.column_id = columnId;
    itemForm.clearErrors(); 

    showItemModal.value = true;
};

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
    itemForm.subtasks = item.subtasks;
    newSubtaskForm.parent_id = item.id;
    showItemModal.value = true;
};

const closeModal = () => { showItemModal.value = false; };

const saveItem = () => {
    if (itemForm.id) {
        itemForm.put(route('items.update', itemForm.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        itemForm.post(route('items.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const addSubtask = () => {
    newSubtaskForm.post(route('subtasks.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            newSubtaskForm.reset('title');
            
        },
    });
};

const toggleSubtask = (subtask) => {
    router.patch(route('subtasks.update', subtask.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const priorityClasses = (p) => ({ 'Baixa': 'bg-gray-400', 'Média': 'bg-yellow-500', 'Alta': 'bg-orange-500', 'Crítica': 'bg-red-600' }[p]);
</script>

<template>
    <Head title="Quadro Kanban" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Quadro Kanban</h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-gray-200 bg-opacity-25 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 p-6 lg:p-8 gap-6">
                    <div v-for="column in boardColumns" :key="column.id" class="bg-gray-100 rounded-lg shadow-md flex flex-col">
                        <div class="p-4 border-b flex justify-between items-center">
                            <h2 class="text-lg font-semibold">{{ column.name }}</h2>
                            <button @click="openCreateItemModal(column.id)" class="text-gray-400 hover:text-blue-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                            </button>
                        </div>
                        <draggable v-model="column.items" group="items" item-key="id" class="p-4 space-y-4 flex-grow" @end="onDragEnd">
                            <template #item="{element: item}">
                                <div @click="openEditItemModal(item)" class="bg-white p-3 rounded-md shadow cursor-pointer">
                                    <div class="flex justify-between items-start">
                                        <h3 class="font-bold text-gray-800">{{ item.title }}</h3>
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" :class="priorityClasses(item.priority)"></span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">{{ item.description }}</p>
                                    <div v-if="item.subtasks && item.subtasks.length > 0" class="mt-3 border-t pt-2 text-xs text-gray-500 italic">
                                        Existem subtarefas, clique para exibir
                                    </div>
                                    <div class="mt-3 flex justify-between items-center border-t pt-2">
                                        <span class="text-xs text-gray-500">#{{ item.id }}</span>
                                        <div v-if="item.estimation" class="text-xs font-bold bg-gray-200 text-gray-700 rounded-full px-2 py-1">{{ item.estimation }} pts</div>
                                        <span class="px-2 py-1 text-xs font-semibold text-white bg-blue-500 rounded-full">{{ item.assignee ? item.assignee.name : 'Não atribuído' }}</span>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showItemModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-2xl font-bold mb-4">{{ itemForm.id ? 'Editar Item' : 'Criar Novo Item' }}</h2>
                <form @submit.prevent="saveItem">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2"><label>Título</label><input type="text" v-model="itemForm.title" class="mt-1 block w-full rounded-md"><div v-if="itemForm.errors.title" class="text-red-500 text-xs">{{ itemForm.errors.title }}</div></div>
                        <div class="md:col-span-2"><label>Descrição</label><textarea v-model="itemForm.description" rows="3" class="mt-1 block w-full rounded-md"></textarea></div>
                        <div><label>Responsável</label><select v-model="itemForm.assignee_id" class="mt-1 block w-full rounded-md"><option :value="null">Não atribuído</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
                        <div><label>Prioridade</label><select v-model="itemForm.priority" class="mt-1 block w-full rounded-md"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                        <div class="md:col-span-2"><label>Estimativa</label><select v-model="itemForm.estimation" class="mt-1 block w-full rounded-md"><option :value="null">Não estimado</option><option v-for="p in [1,2,3,5,8,13,20]" :value="p">{{p}} Pontos</option></select></div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="closeModal" class="px-4 py-2 bg-gray-200 rounded-md">Cancelar</button><button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Salvar</button></div>
                </form>

                <div v-if="itemForm.id" class="mt-6 border-t pt-4">
                    <h3 class="text-lg font-bold mb-2">Subtarefas</h3>
                    <div class="space-y-2">
                        <div v-for="subtask in itemForm.subtasks" :key="subtask.id" class="flex items-center">
                            <input type="checkbox" :checked="!!subtask.completed_at" @change="toggleSubtask(subtask)" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-3 text-sm" :class="{'line-through text-gray-500': subtask.completed_at}">{{ subtask.title }}</span>
                        </div>
                    </div>
                    <form @submit.prevent="addSubtask" class="mt-4 flex items-center space-x-2">
                        <input type="text" v-model="newSubtaskForm.title" placeholder="Adicionar nova subtarefa..." class="flex-grow block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <button type="submit" :disabled="newSubtaskForm.processing" class="px-3 py-1.5 bg-green-500 text-white rounded-md text-sm">Adicionar</button>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
