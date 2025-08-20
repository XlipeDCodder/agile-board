<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Multiselect from 'vue-multiselect';

const props = defineProps({
    items: Object,
    users: Array,
});

const showItemModal = ref(false);

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
});

const openEditItemModal = (item) => {
    itemForm.id = item.id;
    itemForm.title = item.title;
    itemForm.description = item.description;
    itemForm.type = item.type;
    itemForm.priority = item.priority;
    itemForm.assignee_ids = item.assignees.map(user => user.id);
    itemForm.due_date = item.due_date;
    itemForm.column_id = item.column_id;
    itemForm.estimation = item.estimation;
    itemForm.subtasks = item.subtasks || [];
    itemForm.comments = item.comments || [];
    newSubtaskForm.parent_id = item.id;
    newCommentForm.item_id = item.id;
    showItemModal.value = true;
};

const closeModal = () => { showItemModal.value = false; };

const saveItem = () => {
    itemForm.transform(data => ({ ...data, subtasks: undefined, comments: undefined }))
        .put(route('items.update', itemForm.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const addSubtask = () => {
    newSubtaskForm.post(route('subtasks.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            newSubtaskForm.reset('title');
            closeModal();
        },
    });
};

const toggleSubtask = (subtask) => {
    router.patch(route('subtasks.update', subtask.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const addComment = () => {
    newCommentForm.post(route('comments.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => newCommentForm.reset('body'),
    });
};
</script>

<template>
    <Head title="Itens Concluídos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-text-primary leading-tight">Itens Concluídos</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-secondary overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-secondary border-b border-accent">
                        <table class="min-w-full divide-y divide-accent">
                            <thead class="bg-primary">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Título</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Responsáveis</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Prioridade</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Data de Conclusão</th>
                                </tr>
                            </thead>
                            <tbody class="bg-secondary divide-y divide-accent">
                                <tr v-for="item in items.data" :key="item.id" @click="openEditItemModal(item)" class="hover:bg-primary cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-primary">#{{ item.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary">{{ item.title }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{ item.assignees.map(u => u.name).join(', ') || 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{ item.priority }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{ new Date(item.updated_at).toLocaleDateString() }}</td>
                                </tr>
                                <tr v-if="items.data.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-text-secondary">Nenhum item concluído.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="items.links.length > 3" class="p-6 bg-secondary border-t border-accent">
                        <Pagination :links="items.links" />
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showItemModal" @close="closeModal">
             <div class="p-6 bg-secondary text-text-primary">
                <h2 class="text-2xl font-bold mb-4">Editar Item</h2>
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
                        <div class="md:col-span-2"><label class="block text-sm font-medium">Estimativa</label><select v-model="itemForm.estimation" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"><option :value="null">Não estimado</option><option v-for="p in [1,2,3,5,8,13,20]" :value="p">{{p}} Pontos</option></select></div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="closeModal" class="px-4 py-2 bg-accent text-primary rounded-md">Cancelar</button><button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Salvar</button></div>
                </form>

                <div v-if="itemForm.id" class="mt-6 border-t border-accent pt-4">
                    <h3 class="text-lg font-bold mb-2">Subtarefas</h3>
                    <div class="space-y-2"><div v-for="subtask in itemForm.subtasks" :key="subtask.id" class="flex items-center"><input type="checkbox" :checked="!!subtask.completed_at" @change="toggleSubtask(subtask)" class="h-4 w-4 rounded border-accent bg-primary text-indigo-600 focus:ring-indigo-500"><span class="ml-3 text-sm" :class="{'line-through text-text-secondary': subtask.completed_at}">{{ subtask.title }}</span></div></div>
                    <form @submit.prevent="addSubtask" class="mt-4 flex items-center space-x-2"><input type="text" v-model="newSubtaskForm.title" placeholder="Adicionar nova subtarefa..." class="flex-grow block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm text-sm"><button type="submit" :disabled="newSubtaskForm.processing" class="px-3 py-1.5 bg-green-500 text-white rounded-md text-sm">Adicionar</button></form>
                </div>

                <div v-if="itemForm.id" class="mt-6 border-t border-accent pt-4">
                    <h3 class="text-lg font-bold mb-4">Comentários</h3>
                    <form @submit.prevent="addComment" class="mb-4"><textarea v-model="newCommentForm.body" rows="3" placeholder="Adicionar um comentário..." class="w-full rounded-md bg-primary border-accent text-text-primary shadow-sm"></textarea><div v-if="newCommentForm.errors.body" class="text-red-500 text-xs">{{ newCommentForm.errors.body }}</div><button type="submit" :disabled="newCommentForm.processing" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm">Comentar</button></form>
                    <div class="space-y-4 max-h-60 overflow-y-auto"><div v-for="comment in itemForm.comments" :key="comment.id" class="flex items-start space-x-3"><div class="flex-shrink-0"><span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-primary"><span class="text-sm font-medium leading-none text-text-primary">{{ comment.user.name.charAt(0) }}</span></span></div><div class="flex-1"><div class="bg-primary rounded-lg px-3 py-2"><p class="text-sm font-semibold text-text-primary">{{ comment.user.name }}</p><p class="text-sm text-text-primary mt-1 whitespace-pre-wrap">{{ comment.body }}</p></div><span class="text-xs text-text-secondary mt-1">{{ new Date(comment.created_at).toLocaleString() }}</span></div></div></div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
