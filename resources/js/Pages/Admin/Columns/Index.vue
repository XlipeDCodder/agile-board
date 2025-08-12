<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
// 1. Importe o 'watch' do Vue
import { ref, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    columns: Array,
});

const newColumnForm = useForm({
    name: '',
});

const columnList = ref([]);

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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestão de Colunas do Quadro</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Secção para Adicionar Nova Coluna -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Adicionar Nova Coluna</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                A nova coluna será adicionada ao final do quadro.
                            </p>
                        </header>

                        <form @submit.prevent="addColumn" class="mt-6 space-y-6 max-w-xl">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Nome da Coluna</label>
                                <input id="name" type="text" v-model="newColumnForm.name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required />
                                <div v-if="newColumnForm.errors.name" class="text-red-500 text-xs mt-1">{{ newColumnForm.errors.name }}</div>
                            </div>
                            <div class="flex items-center gap-4">
                                <button :disabled="newColumnForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md">Adicionar</button>
                                <transition enter-from-class="opacity-0" leave-to-class="opacity-0" class="transition ease-in-out">
                                    <p v-if="newColumnForm.recentlySuccessful" class="text-sm text-gray-600">Adicionada.</p>
                                </transition>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- Secção para Reordenar Colunas -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Ordenar Colunas</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Arraste e solte as colunas para reordená-las no quadro principal.
                            </p>
                        </header>

                        <div class="mt-6 max-w-xl">
                            <draggable v-model="columnList" item-key="id" handle=".handle" @end="onColumnDragEnd">
                                <template #item="{ element: column }">
                                    <div class="flex items-center justify-between p-3 mb-2 bg-gray-100 rounded-md shadow-sm">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-gray-400 cursor-grab handle mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                            <span class="font-medium">{{ column.name }}</span>
                                        </div>
                                    </div>
                                </template>
                            </draggable>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
