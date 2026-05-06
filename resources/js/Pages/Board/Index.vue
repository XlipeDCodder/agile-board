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
    projects: Array,
});

const boardColumns = ref([]);
const showItemModal = ref(false);
const expandedImage = ref(null);
const activeTab = ref('details');

const itemForm = useForm({
    id: null, title: '', description: '', type: 'task',
    priority: 'Média', assignee_ids: [], due_date: null,
    column_id: null, project_id: null, estimation: null, subtasks: [], comments: [],
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

onMounted(() => { 
    boardColumns.value = props.columns; 
    
    if (window.Echo) {
        window.Echo.channel('board')
            .listen('.item.moved', (e) => {
                router.reload({ only: ['columns'] });
            })
            .listen('.item.created', (e) => {
                router.reload({ only: ['columns'] });
            });
    }
});

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
    itemForm.clearErrors();
    itemForm.id = null;
    itemForm.title = '';
    itemForm.description = '';
    itemForm.type = 'task';
    itemForm.priority = 'Média';
    itemForm.assignee_ids = [];
    itemForm.due_date = null;
    itemForm.project_id = null;
    itemForm.estimation = null;
    itemForm.subtasks = [];
    itemForm.comments = [];
    activeTab.value = 'details';
    
    itemForm.column_id = columnId;
    showItemModal.value = true;
};

const openEditItemModal = (item) => {
    itemForm.reset();
    itemForm.clearErrors();
    
    itemForm.id = item.id;
    itemForm.title = item.title;
    itemForm.description = item.description;
    itemForm.type = item.type;
    itemForm.priority = item.priority;
    itemForm.assignee_ids = item.assignees.map(user => user.id); 
    itemForm.due_date = item.due_date;
    itemForm.column_id = item.column_id;
    itemForm.project_id = item.project_id;
    itemForm.estimation = item.estimation;
    itemForm.subtasks = item.subtasks;
    itemForm.comments = item.comments;
    newSubtaskForm.parent_id = item.id;
    newCommentForm.item_id = item.id;
    activeTab.value = 'details';
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

const handleFiles = (event) => {
    newCommentForm.files = event.target.files;
};

const addComment = () => {
    newCommentForm.post(route('comments.store'), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            newCommentForm.reset('body', 'files');
            const fileInput = document.getElementById('comment-files');
            if (fileInput) fileInput.value = '';
        },
    });
};

const toggleSubtask = (subtask) => {
    router.patch(route('subtasks.update', subtask.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const priorityClasses = (p) => ({ 
    'Baixa': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'Média': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'Alta': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    'Crítica': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
}[p]);

const isBug = (item) => item.type === 'bug';

const isOverdue = (dateString) => {
    if (!dateString) return false;
    const [year, month, day] = dateString.split('-');
    const due = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due < today;
};

const searchQuery = ref('');
const filterProjectId = ref(null);

const matchesFilter = (item) => {
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        const matchesId = item.id.toString().includes(query);
        const matchesTitle = item.title.toLowerCase().includes(query);
        if (!matchesId && !matchesTitle) return false;
    }
    if (filterProjectId.value) {
        if (!item.project_id || item.project_id !== filterProjectId.value) return false;
    }
    return true;
};
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>

<template>
    <Head title="Quadro Kanban" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-4xl text-text-main leading-tight">📋 Quadro Kanban</h2>
            </div>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8">
            <!-- Filtros -->
            <div class="mb-8 flex flex-col md:flex-row gap-4">
                <div class="relative flex-1 max-w-sm">
                    <svg class="absolute inset-y-0 left-3 h-5 w-5 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Buscar por ID ou título..." 
                        class="input-field pl-10 w-full"
                    >
                </div>
                <select v-model="filterProjectId" class="input-field md:w-64">
                    <option :value="null">Todos os Projetos</option>
                    <option v-for="proj in projects" :key="proj.id" :value="proj.id">{{ proj.name }}</option>
                </select>
            </div>

            <!-- Kanban Board -->
            <div class="overflow-x-auto pb-4 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <div class="inline-flex gap-6">
                    <div v-for="column in boardColumns" :key="column.id" class="trello-column">
                        <div class="p-4 border-b border-border-main flex justify-between items-center bg-gradient-to-r from-surface-variant to-surface">
                            <div>
                                <h2 class="text-lg font-bold text-text-main">{{ column.name }}</h2>
                                <p class="text-xs text-text-muted mt-1 font-medium">{{ column.items.length }} itens</p>
                            </div>
                            <button @click="openCreateItemModal(column.id)" class="p-2.5 rounded-lg hover:bg-surface-hover transition text-text-muted hover:text-brand hover:scale-110 active:scale-95">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                            </button>
                        </div>

                        <draggable v-model="column.items" group="items" item-key="id" class="p-4 space-y-3 flex-grow overflow-y-auto" @end="onDragEnd">
                            <template #item="{element: item}">
                                <div v-show="matchesFilter(item)" @click="openEditItemModal(item)" class="trello-card group">
                                    <div class="flex justify-between items-start mb-3">
                                        <h3 class="font-bold text-text-main flex-1 group-hover:text-brand transition line-clamp-2">{{ item.title }}</h3>
                                        <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                                            <span v-if="isBug(item)" class="text-trello-red"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" /></svg></span>
                                            <span v-else class="text-brand"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg></span>
                                        </div>
                                    </div>
                                    <p v-if="item.description" class="text-sm text-text-muted mb-3 line-clamp-2">{{ item.description }}</p>
                                    <div v-if="item.project" class="mb-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold" :class="isOverdue(item.project.due_date) ? 'bg-trello-red/10 text-trello-red' : 'bg-brand/10 text-brand'">{{ item.project.name }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-text-muted border-t border-border-main pt-3 mb-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold">#{{ item.id }}</span>
                                            <span v-if="item.estimation" class="px-2 py-0.5 rounded-full bg-surface text-text-main font-bold text-xs">{{ item.estimation }} pts</span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="priorityClasses(item.priority)">{{ item.priority }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div v-if="item.subtasks?.length > 0" class="text-xs text-text-muted font-medium">📋 {{ item.subtasks.filter(s => s.completed_at).length }}/{{ item.subtasks.length }}</div>
                                        <div class="flex -space-x-2">
                                            <div v-for="assignee in item.assignees.slice(0, 3)" :key="assignee.id" class="h-6 w-6 rounded-full ring-2 ring-surface-variant bg-brand text-white flex items-center justify-center text-xs font-bold" :title="assignee.name">{{ assignee.name.charAt(0).toUpperCase() }}</div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showItemModal" @close="closeModal" max-width="3xl">
            <div class="p-6 bg-surface-variant max-h-[90vh] overflow-y-auto">
                <h2 class="text-2xl font-bold mb-6 text-text-main">{{ itemForm.id ? '✏️ Editar Item' : '➕ Novo Item' }}</h2>
                <div v-if="itemForm.id" class="flex gap-2 mb-6 border-b border-border-main">
                    <button @click="activeTab = 'details'" :class="['px-4 py-2 font-medium border-b-2 transition', activeTab === 'details' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']">📝 Detalhes</button>
                    <button @click="activeTab = 'comments'" :class="['px-4 py-2 font-medium border-b-2 transition', activeTab === 'comments' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']">💬 Comentários ({{ itemForm.comments?.length || 0 }})</button>
                </div>

                <form v-if="activeTab === 'details'" @submit.prevent="saveItem" class="space-y-6">
                    <div><label class="block text-sm font-bold text-text-main mb-2">Título *</label><input type="text" v-model="itemForm.title" class="input-field w-full" required></div>
                    <div><label class="block text-sm font-bold text-text-main mb-2">Descrição</label><textarea v-model="itemForm.description" rows="4" class="input-field w-full"></textarea></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label class="block text-sm font-bold text-text-main mb-2">Tipo</label><select v-model="itemForm.type" class="input-field w-full"><option value="task">Tarefa</option><option value="bug">Bug</option></select></div>
                        <div><label class="block text-sm font-bold text-text-main mb-2">Prioridade</label><select v-model="itemForm.priority" class="input-field w-full"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                        <div class="md:col-span-2"><label class="block text-sm font-bold text-text-main mb-2">Projeto *</label><select v-model="itemForm.project_id" class="input-field w-full" required><option v-for="proj in projects" :key="proj.id" :value="proj.id">{{ proj.name }}</option></select></div>
                        <div class="md:col-span-2"><label class="block text-sm font-bold text-text-main mb-2">Responsáveis</label><Multiselect v-model="itemForm.assignee_ids" :options="users.map(u => u.id)" :custom-label="id => users.find(u => u.id === id)?.name" :multiple="true" placeholder="Selecionar responsáveis"></Multiselect></div>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-border-main"><button type="button" @click="closeModal" class="btn-secondary">Cancelar</button><button type="submit" class="btn-primary">Salvar</button></div>
                </form>

                <div v-if="activeTab === 'comments' && itemForm.id" class="space-y-6">
                    <form @submit.prevent="addComment" class="space-y-4">
                        <textarea v-model="newCommentForm.body" placeholder="Escreva um comentário..." rows="3" class="input-field w-full"></textarea>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-hover border border-border-main text-sm text-text-muted cursor-pointer hover:text-text-main transition">
                                    📎 Anexar arquivos
                                    <input type="file" id="comment-files" @input="newCommentForm.files = $event.target.files" multiple class="hidden">
                                </label>
                                <div v-if="newCommentForm.files?.length > 0" class="mt-2 flex flex-wrap gap-2"><span v-for="f in newCommentForm.files" :key="f.name" class="text-xs px-2 py-1 rounded bg-brand/10 text-brand">{{ f.name }}</span></div>
                            </div>
                            <button type="submit" :disabled="newCommentForm.processing || !newCommentForm.body" class="btn-primary">Enviar</button>
                        </div>
                    </form>

                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <div v-for="comment in itemForm.comments" :key="comment.id" class="p-4 rounded-xl bg-surface border border-border-main shadow-sm">
                            <div class="flex gap-3 mb-3">
                                <div class="h-8 w-8 rounded-full bg-brand text-white flex items-center justify-center text-xs font-bold">{{ comment.user?.name?.charAt(0) }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1"><p class="font-bold text-text-main text-sm">{{ comment.user?.name }}</p><p class="text-xs text-text-muted">{{ new Date(comment.created_at).toLocaleString() }}</p></div>
                                    <p class="text-sm text-text-main whitespace-pre-wrap">{{ comment.body }}</p>
                                    
                                    <div v-if="comment.attachments?.length > 0" class="mt-3 grid grid-cols-2 gap-2 border-t border-border-main/50 pt-3">
                                        <div v-for="attachment in comment.attachments" :key="attachment.id" class="relative group">
                                            <div v-if="attachment.mime_type?.startsWith('image/')" class="cursor-pointer overflow-hidden rounded-lg border border-border-main hover:opacity-90 transition" @click.stop="expandedImage = '/storage/' + attachment.file_path">
                                                <img :src="'/storage/' + attachment.file_path" class="w-full h-24 object-cover">
                                            </div>
                                            <div v-else class="flex items-center p-2 rounded-lg border border-border-main bg-surface-variant hover:bg-surface-hover transition">
                                                <a :href="'/storage/' + attachment.file_path" download target="_blank" @click.stop class="flex items-center gap-2 w-full text-text-main text-xs truncate">
                                                    <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                    <span class="truncate">{{ attachment.file_name }}</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="!!expandedImage" @close="expandedImage = null" max-width="5xl">
            <div class="p-2 bg-black flex justify-center items-center h-full relative min-h-[50vh]" @click="expandedImage = null">
                <img :src="expandedImage" class="max-w-full max-h-[85vh] object-contain">
                <button class="absolute top-4 right-4 text-white hover:text-gray-300" @click.stop="expandedImage = null">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
