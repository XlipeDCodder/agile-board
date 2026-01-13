<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    columns: Array,
});

const newColumnForm = useForm({
    name: '',
});

const columnList = ref([]);
const editingColumnId = ref(null);
const editForm = useForm({
    name: '',
});

onMounted(() => {
    columnList.value = props.columns;
});

watch(() => props.columns, (newColumns) => {
    columnList.value = newColumns;
});

const addColumn = () => {
    newColumnForm.post(route('columns.store'), {
        onSuccess: () => newColumnForm.reset(),
    });
};

const startEditing = (column) => {
    editingColumnId.value = column.id;
    editForm.name = column.name;
};

const cancelEditing = () => {
    editingColumnId.value = null;
    editForm.reset();
};

const updateColumn = (column) => {
    editForm.put(route('columns.update', column.id), {
        onSuccess: () => cancelEditing(),
    });
};

const deleteColumn = (column) => {
    if (column.items_count > 0) {
        alert('Atenção: Esta coluna contém cards. Mova os cards para outra coluna antes de excluir.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir esta coluna?')) {
        router.delete(route('columns.destroy', column.id), {
            preserveScroll: true,
        });
    }
};

const onColumnDragEnd = () => {
    const reorderedIds = columnList.value.map(column => column.id);
    router.patch(route('columns.reorder'), {
        columns: reorderedIds,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Gerir Colunas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-text-primary leading-tight">Gestão de Colunas do Quadro</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Coluna Esquerda: Adicionar -->
                    <div class="p-6 bg-secondary shadow sm:rounded-lg h-fit">
                        <header>
                            <h2 class="text-lg font-medium text-text-primary">Adicionar Nova Coluna</h2>
                            <p class="mt-1 text-sm text-text-secondary">
                                A nova coluna será adicionada ao final do quadro.
                            </p>
                        </header>

                        <form @submit.prevent="addColumn" class="mt-6 space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-text-secondary">Nome da Coluna</label>
                                <input id="name" type="text" v-model="newColumnForm.name" class="mt-1 block w-full rounded-md bg-primary border-accent text-text-primary shadow-sm focus:border-blue-500 focus:ring-blue-500" required />
                                <div v-if="newColumnForm.errors.name" class="text-red-500 text-xs mt-1">{{ newColumnForm.errors.name }}</div>
                            </div>
                            <div class="flex items-center gap-4">
                                <button :disabled="newColumnForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Adicionar</button>
                                <transition enter-from-class="opacity-0" leave-to-class="opacity-0" class="transition ease-in-out">
                                    <p v-if="newColumnForm.recentlySuccessful" class="text-sm text-green-500">Adicionada.</p>
                                </transition>
                            </div>
                        </form>
                    </div>

                    <!-- Coluna Direita: Ordenar e Gerenciar -->
                    <div class="p-6 bg-secondary shadow sm:rounded-lg">
                        <header>
                            <h2 class="text-lg font-medium text-text-primary">Gerenciar Colunas</h2>
                            <p class="mt-1 text-sm text-text-secondary">
                                Arraste para reordenar, clique no lápis para editar ou na lixeira para excluir.
                            </p>
                        </header>

                        <div class="mt-6">
                            <draggable v-model="columnList" item-key="id" handle=".handle" @end="onColumnDragEnd" class="space-y-2">
                                <template #item="{ element: column }">
                                    <div class="flex items-center justify-between p-3 bg-primary rounded-md border border-accent">
                                        
                                        <div class="flex items-center flex-grow mr-4">
                                            <svg class="w-5 h-5 text-text-secondary cursor-grab handle mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                            
                                            <div v-if="editingColumnId === column.id" class="flex-grow flex items-center space-x-2">
                                                <input type="text" v-model="editForm.name" class="block w-full text-sm rounded-md bg-secondary border-accent text-text-primary px-2 py-1" @keyup.enter="updateColumn(column)" @keyup.esc="cancelEditing">
                                                <button @click="updateColumn(column)" class="text-green-500 hover:text-green-400 p-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></button>
                                                <button @click="cancelEditing" class="text-red-500 hover:text-red-400 p-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                            </div>
                                            <span v-else class="font-medium text-text-primary break-all">{{ column.name }}</span>
                                        </div>

                                        <div class="flex items-center space-x-2" v-if="editingColumnId !== column.id">
                                            <button @click="startEditing(column)" class="text-blue-400 hover:text-blue-300 p-1" title="Editar">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>
                                            <button @click="deleteColumn(column)" class="text-red-400 hover:text-red-300 p-1" title="Excluir">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </draggable>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
