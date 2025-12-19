<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';
import Multiselect from 'vue-multiselect';

const props = defineProps({
    columns: Array,
    users: Array,
});

const boardColumns = ref([]);
const showItemModal = ref(false);
const expandedImage = ref(null);

// Formulário principal, agora com 'assignee_ids' para múltiplos responsáveis
const itemForm = useForm({
    id: null, title: '', description: '', type: 'task',
    priority: 'Média', assignee_ids: [], due_date: null,
    column_id: null, estimation: null, subtasks: [], comments: [],
});

const newSubtaskForm = useForm({
    title: '',
    parent_id: null,
});

const newCommentForm = useForm({
    body: '',
    item_id: null,
    files: [],
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
            itemForm.comments = updatedItem.comments;
            itemForm.assignee_ids = updatedItem.assignees.map(user => user.id);
        }
    }
}, { deep: true });

function onDragEnd() {
    const reorderedData = boardColumns.value.map(c => ({ id: c.id, items: c.items.map(i => i.id) }));
    router.patch(route('board.reorder'), { columns: reorderedData }, { preserveScroll: true });
}

const openCreateItemModal = (columnId) => {
    itemForm.reset();
    itemForm.column_id = columnId;
    showItemModal.value = true;
};

const openEditItemModal = (item) => {
    itemForm.id = item.id;
    itemForm.title = item.title;
    itemForm.description = item.description;
    itemForm.type = item.type;
    itemForm.priority = item.priority;
    itemForm.assignee_ids = item.assignees.map(user => user.id); // Mapeia para um array de IDs
    itemForm.due_date = item.due_date;
    itemForm.column_id = item.column_id;
    itemForm.estimation = item.estimation;
    itemForm.subtasks = item.subtasks;
    itemForm.comments = item.comments;
    newSubtaskForm.parent_id = item.id;
    newCommentForm.item_id = item.id;
    showItemModal.value = true;
};

const closeModal = () => { showItemModal.value = false; };

const saveItem = () => {
    if (itemForm.id) {
        itemForm.transform(data => ({ ...data, subtasks: undefined, comments: undefined }))
            .put(route('items.update', itemForm.id), {
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

const addComment = () => {
    newCommentForm.post(route('comments.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            newCommentForm.reset('body', 'files');
            // Limpa o input file manualmente se necessário, mas o reset deve cuidar do v-model se usarmos componente, 
            // mas para input nativo precisamos limpar o valor
            document.getElementById('comment-files').value = null;
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

const isBug = (item) => item.type === 'bug';

</script>

<!-- Estilos para o vue-multiselect e para o tema dark -->
<style src="vue-multiselect/dist/vue-multiselect.css"></style>


<template>
    <Head title="Quadro Kanban" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-text-primary leading-tight">Quadro Kanban</h2>
        </template>

        <div class="py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8">
                <div class="overflow-x-auto pb-4">
                    <div class="inline-flex space-x-6 px-1">
                        <div v-for="column in boardColumns" :key="column.id" class="bg-secondary rounded-lg shadow-md flex flex-col w-80 flex-shrink-0">
                            <div class="p-4 border-b border-accent flex justify-between items-center">
                                <h2 class="text-lg font-semibold text-text-primary">{{ column.name }}</h2>
                                <button @click="openCreateItemModal(column.id)" class="text-text-secondary hover:text-text-primary">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                </button>
                            </div>
                            <draggable v-model="column.items" group="items" item-key="id" class="p-4 space-y-4 flex-grow" @end="onDragEnd">
                                <template #item="{element: item}">
                                    <div @click="openEditItemModal(item)" class="bg-primary p-3 rounded-md shadow cursor-pointer">
                                        <div class="flex justify-between items-start">
                                            <h3 class="font-bold text-text-primary">{{ item.title }}</h3>
                                            <div class="flex items-center space-x-2 flex-shrink-0">
                                                <span v-if="isBug(item)" class="text-red-500" title="Bug">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                </span>
                                                <span v-else class="text-blue-500" title="Tarefa">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                </span>
                                                <span class="w-3 h-3 rounded-full" :class="priorityClasses(item.priority)"></span>
                                            </div>
                                        </div>
                                        <p class="text-sm text-text-secondary mt-2">{{ item.description }}</p>
                                        <div v-if="item.subtasks && item.subtasks.length > 0" class="mt-3 border-t border-accent pt-2 text-xs text-text-secondary italic">
                                            Existem subtarefas, clique para exibir
                                        </div>
                                        <div class="mt-3 flex justify-between items-center border-t border-accent pt-2">
                                            <span class="text-xs text-text-secondary">#{{ item.id }}</span>
                                            <div v-if="item.estimation" class="text-xs font-bold bg-secondary text-text-primary rounded-full px-2 py-1">{{ item.estimation }} pts</div>
                                            <!-- Novo visual para múltiplos responsáveis -->
                                            <div class="flex -space-x-2 overflow-hidden">
                                                <div v-if="item.assignees.length === 0" class="inline-block h-6 w-6 rounded-full bg-gray-500 text-white flex items-center justify-center text-xs" title="Não atribuído">?</div>
                                                <div v-for="assignee in item.assignees" :key="assignee.id" class="inline-block h-6 w-6 rounded-full ring-2 ring-secondary bg-blue-500 text-white flex items-center justify-center text-xs" :title="assignee.name">
                                                    {{ assignee.name.charAt(0) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </draggable>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showItemModal" @close="closeModal">
            <div class="p-6 bg-secondary text-text-primary">
                <h2 class="text-2xl font-bold mb-4">{{ itemForm.id ? 'Editar Item' : 'Criar Novo Item' }}</h2>
                <form @submit.prevent="saveItem">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2"><label class="block text-sm font-medium">Título</label><input type="text" v-model="itemForm.title" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"></div>
                        <div class="md:col-span-2"><label class="block text-sm font-medium">Descrição</label><textarea v-model="itemForm.description" rows="3" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"></textarea></div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium">Responsáveis</label>
                            <Multiselect
                                v-model="itemForm.assignee_ids"
                                :options="users.map(user => user.id)"
                                :custom-label="opt => users.find(user => user.id === opt).name"
                                :multiple="true"
                                placeholder="Selecione os responsáveis"
                                selectLabel="Clique para selecionar"
                                deselectLabel="Clique para remover"
                                selectedLabel="Selecionado"
                                class="mt-1"
                            ></Multiselect>
                        </div>

                        <div><label class="block text-sm font-medium">Prioridade</label><select v-model="itemForm.priority" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                        <div><label class="block text-sm font-medium">Tipo</label><select v-model="itemForm.type" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"><option value="task">Tarefa</option><option value="bug">Bug</option></select></div>
                        <div class="md:col-span-2"><label class="block text-sm font-medium">Estimativa</label><select v-model="itemForm.estimation" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"><option :value="null">Não estimado</option><option v-for="p in [1,2,3,5,8,13,20]" :value="p">{{p}} Pontos</option></select></div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="closeModal" class="px-4 py-2 bg-accent text-primary rounded-md">Cancelar</button><button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Salvar</button></div>
                </form>

                <div v-if="itemForm.id" class="mt-6 border-t border-accent pt-4">
                    <h3 class="text-lg font-bold mb-2">Subtarefas</h3>
                    <div class="space-y-2">
                        <div v-for="subtask in itemForm.subtasks" :key="subtask.id" class="flex items-center">
                            <input type="checkbox" :checked="!!subtask.completed_at" @change="toggleSubtask(subtask)" class="h-4 w-4 rounded border-accent bg-primary text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-3 text-sm" :class="{'line-through text-text-secondary': subtask.completed_at}">{{ subtask.title }}</span>
                        </div>
                    </div>
                    <form @submit.prevent="addSubtask" class="mt-4 flex items-center space-x-2">
                        <input type="text" v-model="newSubtaskForm.title" placeholder="Adicionar nova subtarefa..." class="flex-grow block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm text-sm">
                        <button type="submit" :disabled="newSubtaskForm.processing" class="px-3 py-1.5 bg-green-500 text-white rounded-md text-sm">Adicionar</button>
                    </form>
                </div>

                <div v-if="itemForm.id" class="mt-6 border-t border-accent pt-4">
                    <h3 class="text-lg font-bold mb-4">Comentários</h3>
                    <form @submit.prevent="addComment" class="mb-4">
                        <textarea v-model="newCommentForm.body" rows="3" placeholder="Adicionar um comentário..." class="w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"></textarea>
                        <div v-if="newCommentForm.errors.body" class="text-red-500 text-xs">{{ newCommentForm.errors.body }}</div>
                        <div class="mt-2">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Anexar arquivos</label>
                            <input type="file" id="comment-files" @input="newCommentForm.files = $event.target.files" multiple class="block w-full text-sm text-text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-text-primary hover:file:bg-secondary"/>
                        </div>
                        <button type="submit" :disabled="newCommentForm.processing" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm">Comentar</button>
                    </form>
                    <div class="space-y-4 max-h-60 overflow-y-auto">
                        <div v-for="comment in itemForm.comments" :key="comment.id" class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-primary">
                                    <span class="text-sm font-medium leading-none text-text-primary">{{ comment.user.name.charAt(0) }}</span>
                                </span>
                            </div>
                            <div class="flex-1">
                                <div class="bg-primary rounded-lg px-3 py-2">
                                    <p class="text-sm font-semibold text-text-primary">{{ comment.user.name }}</p>
                                    <p class="text-sm text-text-primary mt-1 whitespace-pre-wrap">{{ comment.body }}</p>
                                    
                                    <div v-if="comment.attachments && comment.attachments.length > 0" class="mt-2 grid grid-cols-2 gap-2">
                                        <div v-for="attachment in comment.attachments" :key="attachment.id" class="relative group">
                                            <div v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" 
                                                 class="cursor-pointer overflow-hidden rounded border border-accent hover:opacity-90 transition"
                                                 @click="expandedImage = '/storage/' + attachment.file_path">
                                                <img :src="'/storage/' + attachment.file_path" class="w-full h-24 object-cover">
                                            </div>
                                            <div v-else class="flex items-center p-2 rounded border border-accent bg-secondary hover:bg-primary transition">
                                                <a :href="'/storage/' + attachment.file_path" download class="flex items-center space-x-2 w-full text-text-primary text-xs">
                                                    <svg class="h-4 w-4 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                    <span class="truncate">{{ attachment.file_name }}</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs text-text-secondary mt-1">{{ new Date(comment.created_at).toLocaleString() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="!!expandedImage" @close="expandedImage = null">
            <div class="p-2 bg-black flex justify-center items-center h-full relative" @click="expandedImage = null">
                <img :src="expandedImage" class="max-w-full max-h-screen object-contain text-white">
                <button class="absolute top-4 right-4 text-white hover:text-gray-300" @click.stop="expandedImage = null">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
