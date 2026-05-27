<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';
import Multiselect from 'vue-multiselect';
import axios from 'axios';

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
    column_id: null, project_id: null, estimation: null,
    predicted_value: null, predicted_unit: 'hours',
    reopened_from_id: null, justification: '',
    subtasks: [], comments: [],
});

// Visão completa do card sendo editado (para acessar campos que não estão no
// itemForm, como is_blocked, blocked_reason, etc).
const currentItem = ref(null);

// Sub-modal de impedimento.
const showBlockModal = ref(false);
const blockForm = useForm({
    reason: '',
    blocked_by_item_id: null,
});

// Toast efêmero (ex: tentou arrastar de Feito).
const toastMessage = ref(null);
let toastTimer = null;
const showToast = (msg) => {
    toastMessage.value = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastMessage.value = null; }, 3500);
};

// Reabertura.
const showReopenModal = ref(false);
const reopenForm = useForm({
    type: 'reabertura',
    reopened_from_id: null,
    title: '',
    description: '',
    justification: '',
    priority: 'Média',
    column_id: null,
    project_id: null,
    estimation: null,
    predicted_value: null,
    predicted_unit: 'hours',
    assignee_ids: [],
});

const columnsExceptDone = computed(() =>
    (boardColumns.value || []).filter(c => c.name !== 'Feito').map(c => ({ id: c.id, name: c.name }))
);

const isInFeito = computed(() => currentItem.value?.column?.name === 'Feito');

// Cards em Feito ganham botões de deploy. Esconde se já tem production
// deploy ativo (regra acordada — evita deploys duplicados sem querer).
const itemDeployments = computed(() => currentItem.value?.deployments || []);
const hasProductionDeploy = computed(() =>
    itemDeployments.value.some(d => d.environment === 'production' && d.status === 'completed')
);
const canRequestDeploy = computed(() => isInFeito.value && !hasProductionDeploy.value);

const deployForm = useForm({
    item_id: null,
    environment: 'staging',
    notes: '',
    is_urgent: false,
});
const showDeployModal = ref(false);
const deployModalMode = ref('staging'); // 'staging' ou 'urgent'

const openDeployModal = (mode) => {
    if (!currentItem.value) return;
    deployForm.reset();
    deployForm.clearErrors();
    deployForm.item_id = currentItem.value.id;
    deployModalMode.value = mode;
    if (mode === 'staging') {
        deployForm.environment = 'staging';
        deployForm.is_urgent = false;
    } else {
        deployForm.environment = 'production';
        deployForm.is_urgent = true;
    }
    showDeployModal.value = true;
};

const submitDeploy = () => {
    deployForm.post(route('deploys.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDeployModal.value = false;
            // Atualiza estado do card no modal (mas como o board é Inertia,
            // não recarrega tudo — o usuário pode fechar e reabrir o card
            // pra ver o estado fresh).
        },
    });
};

const openReopenFromBoard = () => {
    if (!currentItem.value) return;
    const original = currentItem.value;
    reopenForm.reset();
    reopenForm.clearErrors();
    reopenForm.type = 'reabertura';
    reopenForm.reopened_from_id = original.id;
    reopenForm.title = `Reabertura: ${original.title}`;
    reopenForm.description = original.description || '';
    reopenForm.justification = '';
    reopenForm.priority = original.priority;
    reopenForm.column_id = columnsExceptDone.value[0]?.id ?? null;
    reopenForm.project_id = original.project_id;
    reopenForm.estimation = original.estimation;
    reopenForm.predicted_value = original.predicted_value ?? null;
    reopenForm.predicted_unit = original.predicted_unit ?? 'hours';
    reopenForm.assignee_ids = (original.assignees || []).map(u => u.id);
    showReopenModal.value = true;
};

const submitReopen = () => {
    reopenForm.post(route('items.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showReopenModal.value = false;
            closeModal();
        },
    });
};

const allCardsFlat = computed(() => {
    const all = [];
    for (const col of boardColumns.value || []) {
        for (const it of col.items || []) {
            if (it.id !== itemForm.id) {
                all.push({ id: it.id, title: it.title, column: col.name });
            }
        }
    }
    return all;
});

const predictedLabel = (item) => {
    if (!item?.predicted_value || !item?.predicted_unit) return null;
    const v = item.predicted_value;
    const map = {
        minutes: v === 1 ? 'minuto' : 'minutos',
        hours: v === 1 ? 'hora' : 'horas',
        days: v === 1 ? 'dia' : 'dias',
    };
    return `${v} ${map[item.predicted_unit] || item.predicted_unit}`;
};

const newSubtaskForm = useForm({
    title: '',
    parent_id: null,
});

const newCommentForm = useForm({
    body: '',
    item_id: null,
    files: [],
});

let reloadTimeout = null;
const debouncedReload = () => {
    clearTimeout(reloadTimeout);
    reloadTimeout = setTimeout(() => {
        router.reload({ only: ['columns'], preserveScroll: true });
    }, 250);
};

onMounted(() => {
    boardColumns.value = props.columns;

    if (window.Echo) {
        window.Echo.channel('board')
            .listen('.item.moved', debouncedReload)
            .listen('.item.created', debouncedReload)
            .listen('.item.updated', debouncedReload);
    }
});

watch(() => props.columns, (newColumns) => {
    boardColumns.value = newColumns;
    if (showItemModal.value && itemForm.id) {
        let updatedItem = null;
        let updatedColumn = null;
        for (const column of newColumns) {
            const foundItem = column.items.find(item => item.id === itemForm.id);
            if (foundItem) {
                updatedItem = foundItem;
                updatedColumn = column;
                break;
            }
        }
        if (updatedItem) {
            // Atualiza o objeto completo do card (badges de estado do modal
            // dependem dele) e os campos do form que podem mudar via outros
            // endpoints (block/unblock, subtarefas, comentários).
            currentItem.value = { ...updatedItem, column: { id: updatedColumn?.id, name: updatedColumn?.name } };
            itemForm.subtasks = updatedItem.subtasks;
            itemForm.comments = updatedItem.comments;
            itemForm.assignee_ids = updatedItem.assignees.map(user => user.id);
        }
    }
}, { deep: true });

function onDragEnd() {
    const reorderedData = boardColumns.value.map(c => ({ id: c.id, items: c.items.map(i => i.id) }));
    router.patch(route('board.reorder'), { columns: reorderedData }, {
        preserveScroll: true,
        preserveState: true,
        only: [],
        onError: () => {
            // Backend rejeitou (ex: tentou sair de "Feito"). Recarrega para
            // sincronizar com o servidor.
            router.reload({ only: ['columns'], preserveScroll: true });
        },
    });
}

/**
 * Callback do vuedraggable: bloqueia drop quando origem é coluna "Feito"
 * e destino é outra coluna qualquer.
 */
function canMove(evt) {
    // Estratégia: identifica a coluna pelos items (referência ao array).
    const draggedItem = evt.draggedContext?.element;
    if (!draggedItem) return true;
    const fromColumn = boardColumns.value.find(c => c.items.some(i => i.id === draggedItem.id));
    const toItems = evt.relatedContext?.list;
    const toColumn = boardColumns.value.find(c => c.items === toItems);
    if (!fromColumn || !toColumn) return true;
    if (fromColumn.name === 'Feito' && toColumn.id !== fromColumn.id) {
        showToast('🚫 Cards concluídos não podem voltar para colunas anteriores. Abra o card e clique em "🔄 Reabrir card" para criar uma reabertura vinculada.');
        return false;
    }
    return true;
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
    itemForm.predicted_value = null;
    itemForm.predicted_unit = 'hours';
    itemForm.reopened_from_id = null;
    itemForm.justification = '';
    itemForm.subtasks = [];
    itemForm.comments = [];
    currentItem.value = null;
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
    itemForm.predicted_value = item.predicted_value ?? null;
    itemForm.predicted_unit = item.predicted_unit ?? 'hours';
    itemForm.reopened_from_id = item.reopened_from_id ?? null;
    itemForm.justification = item.justification ?? '';
    itemForm.subtasks = item.subtasks;
    itemForm.comments = item.comments;
    // Anexa a coluna ao currentItem: o objeto vindo de column.items NÃO traz a
    // relação .column eager-loaded (só column_id), e o computed isInFeito
    // depende de .column.name. Sem isso, o botão "Reabrir card" não aparecia
    // na primeira abertura do modal — só depois do watch sincronizar.
    const col = boardColumns.value.find(c => c.id === item.column_id);
    currentItem.value = { ...item, column: { id: col?.id, name: col?.name } };
    newSubtaskForm.parent_id = item.id;
    newCommentForm.item_id = item.id;
    activeTab.value = 'details';
    showItemModal.value = true;
};

const openBlockModal = () => {
    blockForm.reset();
    blockForm.clearErrors();
    showBlockModal.value = true;
};

const submitBlock = () => {
    blockForm.post(route('items.block', itemForm.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['columns'],
        onSuccess: () => {
            showBlockModal.value = false;
            blockForm.reset();
        },
    });
};

const unblockCard = () => {
    if (!itemForm.id) return;
    router.post(route('items.unblock', itemForm.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['columns'],
    });
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

const pokerValues = [1, 2, 3, 5, 8, 13, 20];

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

                        <draggable v-model="column.items" group="items" item-key="id" class="p-4 space-y-3 flex-grow overflow-y-auto" @end="onDragEnd" :move="canMove">
                            <template #item="{element: item}">
                                <div v-show="matchesFilter(item)" @click="openEditItemModal(item)"
                                    class="trello-card group"
                                    :class="item.is_blocked ? 'border-2 border-trello-red/60' : ''">
                                    <!-- Badges de estado -->
                                    <div v-if="item.is_blocked || item.type === 'reabertura' || (item.predicted_value && item.predicted_unit)" class="flex flex-wrap gap-1.5 mb-2">
                                        <span v-if="item.is_blocked" :title="item.blocked_reason || 'Impedido'"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-trello-red/10 text-trello-red border border-trello-red/30">
                                            🚫 Impedido
                                        </span>
                                        <span v-if="item.type === 'reabertura'" :title="`Reaberto do card #${item.reopened_from_id}`"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-orange-500/10 text-orange-500 border border-orange-500/30">
                                            🔄 Reaberto de #{{ item.reopened_from_id }}
                                        </span>
                                        <span v-if="item.predicted_value && item.predicted_unit"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-surface text-text-muted border border-border-main">
                                            ⏱️ {{ predictedLabel(item) }}
                                        </span>
                                    </div>

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
                <div class="flex items-start justify-between gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-text-main">
                        {{ itemForm.id ? '✏️ Editar Item' : '➕ Novo Item' }}
                        <span v-if="itemForm.type === 'reabertura'" class="text-base font-medium text-orange-500 ml-2">🔄 Reabertura</span>
                    </h2>
                    <div v-if="itemForm.id" class="flex items-center gap-2 flex-wrap justify-end">
                        <button v-if="canRequestDeploy"
                            type="button"
                            @click="openDeployModal('staging')"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-amber-500/10 text-amber-600 border border-amber-500/30 hover:bg-amber-500/20 transition">
                            🚀 Solicitar deploy em homologação
                        </button>
                        <button v-if="canRequestDeploy"
                            type="button"
                            @click="openDeployModal('urgent')"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-trello-red/10 text-trello-red border border-trello-red/30 hover:bg-trello-red/20 transition">
                            ⚠️ Deploy urgente em produção
                        </button>
                        <button v-if="isInFeito && hasProductionDeploy"
                            type="button"
                            disabled
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-500/10 text-emerald-600 border border-emerald-500/30 cursor-not-allowed">
                            ✅ Já em produção
                        </button>
                        <button v-if="isInFeito"
                            type="button"
                            @click="openReopenFromBoard"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-orange-500/10 text-orange-500 border border-orange-500/30 hover:bg-orange-500/20 transition">
                            🔄 Reabrir card
                        </button>
                        <!--
                            Cards em "Feito" não têm controles de impedimento:
                            um card concluído não pode estar impedido. O backend
                            também auto-desimpede ao mover pra Feito.
                        -->
                        <button v-if="currentItem?.is_blocked && !isInFeito"
                            type="button"
                            @click="unblockCard"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-500/10 text-emerald-500 border border-emerald-500/30 hover:bg-emerald-500/20 transition">
                            ✅ Desimpedir
                        </button>
                        <button v-else-if="!isInFeito"
                            type="button"
                            @click="openBlockModal"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-trello-red/10 text-trello-red border border-trello-red/30 hover:bg-trello-red/20 transition">
                            🚫 Marcar como impedimento
                        </button>
                    </div>
                </div>

                <div v-if="currentItem?.is_blocked" class="mb-4 p-3 rounded-xl bg-trello-red/10 border border-trello-red/30 text-sm">
                    <p class="font-bold text-trello-red mb-1">🚫 Card impedido</p>
                    <p class="text-text-main"><strong>Motivo:</strong> {{ currentItem.blocked_reason }}</p>
                    <p v-if="currentItem.blocked_by_item_id" class="text-text-muted mt-1">Bloqueado pelo card #{{ currentItem.blocked_by_item_id }}</p>
                </div>

                <div v-if="itemForm.id" class="flex gap-2 mb-6 border-b border-border-main">
                    <button @click="activeTab = 'details'" :class="['px-4 py-2 font-medium border-b-2 transition', activeTab === 'details' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']">📝 Detalhes</button>
                    <button @click="activeTab = 'subtasks'" :class="['px-4 py-2 font-medium border-b-2 transition', activeTab === 'subtasks' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']">📋 Subtarefas ({{ itemForm.subtasks?.filter(s => s.completed_at).length || 0 }}/{{ itemForm.subtasks?.length || 0 }})</button>
                    <button @click="activeTab = 'comments'" :class="['px-4 py-2 font-medium border-b-2 transition', activeTab === 'comments' ? 'border-brand text-brand' : 'border-transparent text-text-muted hover:text-text-main']">💬 Comentários ({{ itemForm.comments?.length || 0 }})</button>
                </div>

                <form v-if="activeTab === 'details'" @submit.prevent="saveItem" class="space-y-6">
                    <div><label class="block text-sm font-bold text-text-main mb-2">Título *</label><input type="text" v-model="itemForm.title" class="input-field w-full" required></div>
                    <div><label class="block text-sm font-bold text-text-main mb-2">Descrição</label><textarea v-model="itemForm.description" rows="4" class="input-field w-full"></textarea></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label class="block text-sm font-bold text-text-main mb-2">Tipo</label><select v-model="itemForm.type" class="input-field w-full"><option value="task">Tarefa</option><option value="bug">Bug</option></select></div>
                        <div><label class="block text-sm font-bold text-text-main mb-2">Prioridade</label><select v-model="itemForm.priority" class="input-field w-full"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                        <div class="md:col-span-2"><label class="block text-sm font-bold text-text-main mb-2">Projeto *</label><select v-model="itemForm.project_id" class="input-field w-full" required><option v-for="proj in projects" :key="proj.id" :value="proj.id">{{ proj.name }}</option></select></div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Dificuldade <span class="font-normal text-text-muted">(Planning Poker)</span></label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="value in pokerValues"
                                    :key="value"
                                    type="button"
                                    @click="itemForm.estimation = itemForm.estimation === value ? null : value"
                                    :class="[
                                        'px-4 py-2 rounded-lg font-bold text-sm transition border-2 active:scale-95',
                                        itemForm.estimation === value
                                            ? 'bg-brand text-white border-brand shadow-md scale-105'
                                            : 'bg-surface text-text-main border-border-main hover:bg-surface-hover hover:border-brand/50'
                                    ]"
                                >
                                    {{ value }}
                                </button>
                                <button
                                    type="button"
                                    @click="itemForm.estimation = null"
                                    :class="[
                                        'px-3 py-2 rounded-lg font-medium text-xs transition border-2',
                                        itemForm.estimation === null
                                            ? 'bg-surface-hover text-text-main border-border-main'
                                            : 'bg-transparent text-text-muted border-transparent hover:text-text-main'
                                    ]"
                                >
                                    Sem estimativa
                                </button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Previsão de término <span class="font-normal text-text-muted">(opcional)</span></label>
                            <div class="flex gap-2">
                                <input v-model.number="itemForm.predicted_value" type="number" min="1" max="9999"
                                    placeholder="Quantidade" class="input-field flex-1">
                                <select v-model="itemForm.predicted_unit" class="input-field w-40">
                                    <option value="minutes">Minutos</option>
                                    <option value="hours">Horas</option>
                                    <option value="days">Dias</option>
                                </select>
                            </div>
                            <p class="text-xs text-text-muted mt-1">Estimativa absoluta de tempo (diferente da Dificuldade, que é complexidade relativa).</p>
                        </div>
                        <div v-if="itemForm.type === 'reabertura'" class="md:col-span-2 p-4 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-3">
                            <div>
                                <label class="block text-sm font-bold text-text-main mb-1">🔄 Card original</label>
                                <p class="text-sm text-text-main">#{{ itemForm.reopened_from_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-main mb-1">Justificativa da reabertura <span class="text-text-muted font-normal text-xs">(imutável)</span></label>
                                <p class="text-sm text-text-main whitespace-pre-wrap">{{ itemForm.justification }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-2"><label class="block text-sm font-bold text-text-main mb-2">Responsáveis</label><Multiselect v-model="itemForm.assignee_ids" :options="users.map(u => u.id)" :custom-label="id => users.find(u => u.id === id)?.name" :multiple="true" placeholder="Selecionar responsáveis"></Multiselect></div>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-border-main"><button type="button" @click="closeModal" class="btn-secondary">Cancelar</button><button type="submit" class="btn-primary">Salvar</button></div>
                </form>

                <div v-if="activeTab === 'subtasks' && itemForm.id" class="space-y-4">
                    <div v-if="itemForm.subtasks?.length === 0" class="text-center py-8 text-text-muted">
                        Nenhuma subtarefa ainda. Adicione abaixo. 👇
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="subtask in itemForm.subtasks" :key="subtask.id"
                            class="flex items-center gap-3 p-3 rounded-xl bg-surface border border-border-main hover:border-brand/40 transition group">
                            <input type="checkbox"
                                :checked="!!subtask.completed_at"
                                @change="toggleSubtask(subtask)"
                                class="h-5 w-5 rounded border-border-main bg-surface-variant text-brand focus:ring-brand focus:ring-2 cursor-pointer flex-shrink-0">
                            <span class="flex-1 text-sm transition"
                                :class="subtask.completed_at ? 'line-through text-text-muted' : 'text-text-main'">
                                {{ subtask.title }}
                            </span>
                            <span v-if="subtask.completed_at" class="text-xs text-text-muted">
                                ✓ {{ new Date(subtask.completed_at).toLocaleDateString('pt-BR') }}
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="addSubtask" class="flex gap-2 pt-4 border-t border-border-main">
                        <input type="text" v-model="newSubtaskForm.title"
                            placeholder="Nova subtarefa..."
                            class="input-field flex-1" required>
                        <button type="submit" :disabled="newSubtaskForm.processing || !newSubtaskForm.title"
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                            + Adicionar
                        </button>
                    </form>
                </div>

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

        <!-- Toast efêmero (canto inferior central) -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="toastMessage"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 max-w-md px-4 py-3 rounded-xl bg-trello-red text-white shadow-2xl border border-trello-red/40 text-sm font-medium">
                {{ toastMessage }}
            </div>
        </transition>

        <!-- Sub-modal: reabrir card concluído (acessível diretamente do Board) -->
        <Modal :show="showReopenModal" @close="showReopenModal = false" max-width="3xl">
            <div class="p-6 bg-surface-variant max-h-[90vh] overflow-y-auto">
                <h3 class="text-2xl font-bold text-text-main mb-1">🔄 Reabrir card</h3>
                <p class="text-sm text-text-muted mb-5" v-if="currentItem">
                    Criando uma reabertura vinculada ao card <strong class="text-text-main">#{{ currentItem.id }} "{{ currentItem.title }}"</strong>. O card original permanecerá em Feito.
                </p>

                <form @submit.prevent="submitReopen" class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Justificativa da reabertura *</label>
                        <textarea v-model="reopenForm.justification" rows="3" required maxlength="5000"
                            placeholder="Por que este card precisa ser reaberto?"
                            class="input-field w-full"></textarea>
                        <div v-if="reopenForm.errors.justification" class="text-trello-red text-xs mt-1">{{ reopenForm.errors.justification }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Título *</label>
                        <input v-model="reopenForm.title" type="text" required maxlength="255" class="input-field w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Descrição</label>
                        <textarea v-model="reopenForm.description" rows="3" class="input-field w-full"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-text-main mb-2">Coluna inicial *</label>
                            <select v-model="reopenForm.column_id" class="input-field w-full" required>
                                <option v-for="col in columnsExceptDone" :key="col.id" :value="col.id">{{ col.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-main mb-2">Prioridade</label>
                            <select v-model="reopenForm.priority" class="input-field w-full">
                                <option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Projeto *</label>
                            <select v-model="reopenForm.project_id" class="input-field w-full" required>
                                <option v-for="proj in projects" :key="proj.id" :value="proj.id">{{ proj.name }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Previsão de término <span class="font-normal text-text-muted">(opcional)</span></label>
                            <div class="flex gap-2">
                                <input v-model.number="reopenForm.predicted_value" type="number" min="1" max="9999" placeholder="Quantidade" class="input-field flex-1">
                                <select v-model="reopenForm.predicted_unit" class="input-field w-40">
                                    <option value="minutes">Minutos</option>
                                    <option value="hours">Horas</option>
                                    <option value="days">Dias</option>
                                </select>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-main mb-2">Responsáveis</label>
                            <Multiselect v-model="reopenForm.assignee_ids"
                                :options="users.map(u => u.id)"
                                :custom-label="id => users.find(u => u.id === id)?.name"
                                :multiple="true"
                                placeholder="Selecionar responsáveis"></Multiselect>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-border-main">
                        <button type="button" @click="showReopenModal = false" class="btn-secondary">Cancelar</button>
                        <button type="submit"
                            :disabled="reopenForm.processing || !reopenForm.justification || !reopenForm.title || !reopenForm.column_id"
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                            🔄 Criar reabertura
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Sub-modal: marcar como impedimento -->
        <Modal :show="showBlockModal" @close="showBlockModal = false" max-width="lg">
            <div class="p-6 bg-surface-variant">
                <h3 class="text-lg font-bold text-text-main mb-1">🚫 Marcar como impedimento</h3>
                <p class="text-sm text-text-muted mb-5">Descreva por que o card está impedido. Opcionalmente, indique qual outro card está causando o bloqueio.</p>

                <form @submit.prevent="submitBlock" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Motivo *</label>
                        <textarea v-model="blockForm.reason" rows="3" required maxlength="1000"
                            placeholder="Ex: aguardando aprovação do cliente / dependência externa / ambiente fora do ar..."
                            class="input-field w-full"></textarea>
                        <div v-if="blockForm.errors.reason" class="text-trello-red text-xs mt-1">{{ blockForm.errors.reason }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-text-main mb-2">Bloqueado por qual card? <span class="font-normal text-text-muted">(opcional)</span></label>
                        <select v-model="blockForm.blocked_by_item_id" class="input-field w-full">
                            <option :value="null">— Nenhum card específico —</option>
                            <option v-for="c in allCardsFlat" :key="c.id" :value="c.id">
                                #{{ c.id }} {{ c.title }} ({{ c.column }})
                            </option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-border-main">
                        <button type="button" @click="showBlockModal = false" class="btn-secondary">Cancelar</button>
                        <button type="submit" :disabled="blockForm.processing || !blockForm.reason"
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                            Marcar como impedido
                        </button>
                    </div>
                </form>
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

        <!-- Sub-modal: solicitar deploy (staging ou urgente em produção) -->
        <Modal :show="showDeployModal" @close="showDeployModal = false" max-width="lg">
            <form @submit.prevent="submitDeploy" class="p-6 bg-surface-variant space-y-4">
                <h3 class="text-lg font-bold text-text-main">
                    <span v-if="deployModalMode === 'staging'">🚀 Solicitar deploy em homologação</span>
                    <span v-else class="text-trello-red">⚠️ Deploy urgente em produção</span>
                </h3>
                <p v-if="deployModalMode === 'urgent'" class="text-sm text-trello-red bg-trello-red/10 border border-trello-red/30 rounded-lg p-3">
                    <strong>Atenção:</strong> esse deploy <strong>pula a etapa de homologação</strong>. Use só em casos de hotfix ou emergência. Os admins serão notificados.
                </p>
                <p v-else class="text-sm text-text-muted">
                    Todos os admins serão notificados pra aprovar ou rejeitar. Você não será notificado do próprio deploy.
                </p>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Notas (opcional)</label>
                    <textarea v-model="deployForm.notes"
                              rows="4"
                              class="input-field w-full"
                              maxlength="2000"
                              placeholder="Release notes, versão, link da pipeline, observações…"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2 border-t border-border-main">
                    <button type="button" @click="showDeployModal = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" :disabled="deployForm.processing"
                            :class="[
                                'px-4 py-2 rounded-lg font-bold text-white disabled:opacity-50',
                                deployModalMode === 'urgent' ? 'bg-trello-red' : 'bg-amber-500'
                            ]">
                        {{ deployForm.processing ? 'Enviando…' : (deployModalMode === 'urgent' ? 'Confirmar deploy urgente' : 'Solicitar') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
