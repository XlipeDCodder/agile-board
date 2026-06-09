<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import MarkdownEditor from '@/Components/MarkdownEditor.vue';
import MarkdownViewer from '@/Components/MarkdownViewer.vue';
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Multiselect from 'vue-multiselect';

const props = defineProps({
    items: Object,
    users: Array,
    projects: Array,
});

const showItemModal = ref(false);
const expandedImage = ref(null);
const activeTab = ref('details');

const pokerValues = [1, 2, 3, 5, 8, 13, 20];

const itemForm = useForm({
    id: null, title: '', description: '', type: 'task',
    priority: 'Média', assignee_ids: [], due_date: null,
    column_id: null, project_id: null, estimation: null,
    predicted_value: null, predicted_unit: 'hours',
    reopened_from_id: null, justification: '',
    subtasks: [], comments: [],
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

const openEditItemModal = (item) => {
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
    itemForm.predicted_value = item.predicted_value ?? null;
    itemForm.predicted_unit = item.predicted_unit ?? 'hours';
    itemForm.reopened_from_id = item.reopened_from_id ?? null;
    itemForm.justification = item.justification ?? '';
    itemForm.subtasks = item.subtasks || [];
    itemForm.comments = item.comments || [];
    newSubtaskForm.parent_id = item.id;
    newCommentForm.item_id = item.id;
    activeTab.value = 'details';
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
        forceFormData: true,
        onSuccess: () => {
            newCommentForm.reset('body', 'files');
            const fileInput = document.getElementById('backlog-comment-files');
            if (fileInput) fileInput.value = '';
        },
    });
};
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>

<template>
    <Head title="Backlog" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-text-primary leading-tight inline-flex items-center gap-2"><Icon name="backlog" :size="22" /> Backlog de Tarefas</h2>
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Responsáveis</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Prioridade</th>
                                </tr>
                            </thead>
                            <tbody class="bg-secondary divide-y divide-accent">
                                <tr v-for="item in items.data" :key="item.id" @click="openEditItemModal(item)" class="hover:bg-primary cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-primary">#{{ item.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary">
                                        <div class="flex items-center gap-2">
                                            <span>{{ item.title }}</span>
                                            <span v-if="item.is_blocked" title="Impedido" class="text-trello-red"><Icon name="block" :size="14" /></span>
                                            <span v-if="item.type === 'reabertura'" :title="`Reaberto de #${item.reopened_from_id}`" class="text-orange-500"><Icon name="reopen" :size="14" /></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{ item.column.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{ item.assignees.map(u => u.name).join(', ') || 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{ item.priority }}</td>
                                </tr>
                                <tr v-if="items.data.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-text-secondary">Nenhum item no backlog.</td>
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

        <Modal :show="showItemModal" @close="closeModal" max-width="3xl">
            <div class="p-6 bg-surface-variant max-h-[90vh] overflow-y-auto">
                <h2 class="text-2xl font-bold mb-6 text-text-main inline-flex items-center gap-2"><Icon name="edit" :size="22" /> Editar Item</h2>

                <div v-if="itemForm.id" class="flex gap-2 mb-6 border-b border-border-main">
                    <button @click="activeTab = 'details'" :class="['inline-flex items-center gap-1.5 px-4 py-2 font-medium border-b-2 transition', activeTab === 'details' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']"><Icon name="details" :size="16" /> Detalhes</button>
                    <button @click="activeTab = 'subtasks'" :class="['inline-flex items-center gap-1.5 px-4 py-2 font-medium border-b-2 transition', activeTab === 'subtasks' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']"><Icon name="subtasks" :size="16" /> Subtarefas ({{ itemForm.subtasks?.filter(s => s.completed_at).length || 0 }}/{{ itemForm.subtasks?.length || 0 }})</button>
                    <button @click="activeTab = 'comments'" :class="['inline-flex items-center gap-1.5 px-4 py-2 font-medium border-b-2 transition', activeTab === 'comments' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']"><Icon name="comments" :size="16" /> Comentários ({{ itemForm.comments?.length || 0 }})</button>
                </div>

                <form v-if="activeTab === 'details'" @submit.prevent="saveItem" class="space-y-6">
                    <div><label class="block text-sm font-bold text-text-main mb-2">Título *</label><input type="text" v-model="itemForm.title" class="input-field w-full" required></div>
                    <div><label class="block text-sm font-bold text-text-main mb-2">Descrição</label><MarkdownEditor v-model="itemForm.description" :rows="5" placeholder="Descreva o card em Markdown… (cole imagens com Ctrl+V)" /></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label class="block text-sm font-bold text-text-main mb-2">Tipo</label><select v-model="itemForm.type" class="input-field w-full"><option value="task">Tarefa</option><option value="bug">Bug</option></select></div>
                        <div><label class="block text-sm font-bold text-text-main mb-2">Prioridade</label><select v-model="itemForm.priority" class="input-field w-full"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Projeto *</label>
                            <select v-model="itemForm.project_id" class="input-field w-full" required>
                                <option :value="null" disabled>Selecione um projeto</option>
                                <option v-for="proj in projects" :key="proj.id" :value="proj.id">{{ proj.name }}</option>
                            </select>
                            <div v-if="itemForm.errors.project_id" class="text-trello-red text-xs mt-1">{{ itemForm.errors.project_id }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Dificuldade <span class="font-normal text-text-muted">(Planning Poker)</span></label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="value in pokerValues" :key="value" type="button"
                                    @click="itemForm.estimation = itemForm.estimation === value ? null : value"
                                    :class="[
                                        'px-4 py-2 rounded-lg font-bold text-sm transition border-2 active:scale-95',
                                        itemForm.estimation === value
                                            ? 'bg-brand text-white border-brand shadow-md scale-105'
                                            : 'bg-surface text-text-main border-border-main hover:bg-surface-hover hover:border-brand/50'
                                    ]">{{ value }}</button>
                                <button type="button" @click="itemForm.estimation = null"
                                    :class="[
                                        'px-3 py-2 rounded-lg font-medium text-xs transition border-2',
                                        itemForm.estimation === null
                                            ? 'bg-surface-hover text-text-main border-border-main'
                                            : 'bg-transparent text-text-muted border-transparent hover:text-text-main'
                                    ]">Sem estimativa</button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Previsão de término <span class="font-normal text-text-muted">(opcional)</span></label>
                            <div class="flex gap-2">
                                <input v-model.number="itemForm.predicted_value" type="number" min="1" max="9999" placeholder="Quantidade" class="input-field flex-1">
                                <select v-model="itemForm.predicted_unit" class="input-field w-40">
                                    <option value="minutes">Minutos</option>
                                    <option value="hours">Horas</option>
                                    <option value="days">Dias</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="itemForm.type === 'reabertura'" class="md:col-span-2 p-4 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-3">
                            <div>
                                <label class="block text-sm font-bold text-text-main mb-1 inline-flex items-center gap-1.5"><Icon name="reopen" :size="14" /> Card original</label>
                                <p class="text-sm text-text-main">#{{ itemForm.reopened_from_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-main mb-1">Justificativa <span class="text-text-muted font-normal text-xs">(imutável)</span></label>
                                <p class="text-sm text-text-main whitespace-pre-wrap">{{ itemForm.justification }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-2"><label class="block text-sm font-bold text-text-main mb-2">Responsáveis</label><Multiselect v-model="itemForm.assignee_ids" :options="users.map(u => u.id)" :custom-label="id => users.find(u => u.id === id)?.name" :multiple="true" placeholder="Selecionar responsáveis"></Multiselect></div>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-border-main">
                        <button type="button" @click="closeModal" class="btn-secondary">Cancelar</button>
                        <button type="submit" :disabled="itemForm.processing" class="btn-primary">Salvar</button>
                    </div>
                </form>

                <div v-if="activeTab === 'subtasks' && itemForm.id" class="space-y-4">
                    <div v-if="itemForm.subtasks?.length === 0" class="text-center py-8 text-text-muted">
                        Nenhuma subtarefa ainda. Adicione abaixo. 👇
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="subtask in itemForm.subtasks" :key="subtask.id"
                            class="flex items-center gap-3 p-3 rounded-xl bg-surface border border-border-main hover:border-brand/40 transition group">
                            <input type="checkbox" :checked="!!subtask.completed_at" @change="toggleSubtask(subtask)"
                                class="h-5 w-5 rounded border-border-main bg-surface-variant text-brand focus:ring-brand focus:ring-2 cursor-pointer flex-shrink-0">
                            <span class="flex-1 text-sm transition"
                                :class="subtask.completed_at ? 'line-through text-text-muted' : 'text-text-main'">{{ subtask.title }}</span>
                            <span v-if="subtask.completed_at" class="inline-flex items-center gap-1 text-xs text-text-muted"><Icon name="check" :size="13" class="text-emerald-500" /> {{ new Date(subtask.completed_at).toLocaleDateString('pt-BR') }}</span>
                        </div>
                    </div>
                    <form @submit.prevent="addSubtask" class="flex gap-2 pt-4 border-t border-border-main">
                        <input type="text" v-model="newSubtaskForm.title" placeholder="Nova subtarefa..." class="input-field flex-1" required>
                        <button type="submit" :disabled="newSubtaskForm.processing || !newSubtaskForm.title" class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">+ Adicionar</button>
                    </form>
                </div>

                <div v-if="activeTab === 'comments' && itemForm.id" class="space-y-6">
                    <form @submit.prevent="addComment" class="space-y-4">
                        <MarkdownEditor v-model="newCommentForm.body" :rows="3" placeholder="Escreva um comentário em Markdown… (cole imagens com Ctrl+V)" />
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-hover border border-border-main text-sm text-text-muted cursor-pointer hover:text-text-main transition">
                                    <Icon name="attach" :size="16" /> Anexar arquivos
                                    <input type="file" id="backlog-comment-files" @input="newCommentForm.files = $event.target.files" multiple class="hidden">
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
                                    <MarkdownViewer :source="comment.body" class="text-sm text-text-main" />
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
