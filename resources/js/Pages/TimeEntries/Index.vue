<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    entries: Array,
    items: Array,
});

const currentDate = ref(new Date());
const showModal = ref(false);
const selectedDate = ref(null);

const form = useForm({
    item_id: null,
    date: null,
    hours: 0,
    minutes: 0,
});

const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

const currentMonthName = computed(() => monthNames[currentDate.value.getMonth()]);
const currentYear = computed(() => currentDate.value.getFullYear());

const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const date = new Date(year, month, 1);
    const days = [];
    while (date.getMonth() === month) {
        days.push(new Date(date));
        date.setDate(date.getDate() + 1);
    }
    return days;
});

const blankDays = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const firstDayOfMonth = new Date(year, month, 1).getDay();
    return Array(firstDayOfMonth).fill(null);
});

const formatDate = (date) => {
    return date.toISOString().split('T')[0];
};

const isToday = (date) => {
    const today = new Date();
    return date.getDate() === today.getDate() &&
           date.getMonth() === today.getMonth() &&
           date.getFullYear() === today.getFullYear();
};

const getEntriesForDate = (date) => {
    const dateString = formatDate(date);
    return props.entries.filter(entry => entry.date === dateString);
};

const getTotalTimeForDate = (date) => {
    const entries = getEntriesForDate(date);
    const totalMinutes = entries.reduce((acc, entry) => acc + entry.minutes, 0);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h ${minutes}m`;
};

const isEditing = ref(false);
const editingEntryId = ref(null);

const openModal = (date) => {
    selectedDate.value = date;
    cancelEdit(); // Reset to add mode
    form.clearErrors();
    form.date = formatDate(date);
    showModal.value = true;
};

const editEntry = (entry) => {
    isEditing.value = true;
    editingEntryId.value = entry.id;
    form.clearErrors();
    form.item_id = entry.item_id;
    form.hours = Math.floor(entry.minutes / 60);
    form.minutes = entry.minutes % 60;
};

const cancelEdit = () => {
    isEditing.value = false;
    editingEntryId.value = null;
    form.reset('item_id', 'hours', 'minutes');
    form.clearErrors();
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('time-entries.update', editingEntryId.value), {
            onSuccess: () => {
                cancelEdit();
                // Keep modal open to see changes or close? 
                // User requirement: "modal... mostre a lista... com botão de deletar ou alterar". 
                // So typically we keep it open or reset form. Let's keep open and reset form.
            },
        });
    } else {
        form.post(route('time-entries.store'), {
            onSuccess: () => {
                cancelEdit(); 
            },
        });
    }
};

const deleteEntry = (id) => {
    if(confirm('Tem certeza que deseja remover este apontamento?')) {
        router.delete(route('time-entries.destroy', id), { 
            preserveScroll: true,
            onSuccess: () => {
                if (editingEntryId.value === id) cancelEdit();
            }
        });
    }
}

const formatTime = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
};
</script>

<template>
    <Head title="Apontamento de Horas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-text-primary leading-tight">Apontamento de Horas</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-secondary overflow-hidden shadow-sm sm:rounded-lg p-6">
                    
                    <!-- Calendar Header -->
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-text-primary">{{ currentMonthName }} {{ currentYear }}</h3>
                        <div class="flex space-x-2">
                            <button @click="prevMonth" class="px-3 py-1 bg-primary border border-accent rounded hover:bg-accent text-text-primary">&lt;</button>
                            <button @click="nextMonth" class="px-3 py-1 bg-primary border border-accent rounded hover:bg-accent text-text-primary">&gt;</button>
                        </div>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="grid grid-cols-7 gap-2 mb-2">
                        <div v-for="day in ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']" :key="day" class="text-center font-bold text-text-secondary">
                            {{ day }}
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-2">
                        <div v-for="(blank, index) in blankDays" :key="'blank-'+index" class="h-32 bg-transparent"></div>
                        <div v-for="date in daysInMonth" :key="date" 
                             @click="openModal(date)"
                             class="h-32 bg-primary border border-accent rounded p-2 cursor-pointer hover:bg-opacity-80 transition relative flex flex-col"
                             :class="{'border-blue-500': isToday(date)}">
                            <span class="font-bold text-text-primary self-end">{{ date.getDate() }}</span>
                            
                            <!-- Daily Total ONLY (Cleaner View) -->
                            <div class="flex-grow flex items-center justify-center">
                                <div class="text-lg font-bold text-blue-500" v-if="getTotalTimeForDate(date) !== '0h 0m'">
                                    {{ getTotalTimeForDate(date) }}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false" maxWidth="4xl">
            <div class="p-6 bg-secondary text-text-primary flex flex-col md:flex-row gap-6">
                <!-- Left Column: List of Entries -->
                <div class="md:w-1/2 border-r border-accent pr-6">
                    <h3 class="text-xl font-bold mb-4">Apontamentos do Dia ({{ selectedDate ? formatDate(selectedDate) : '' }})</h3>
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar">
                        <div v-for="entry in getEntriesForDate(selectedDate ? new Date(selectedDate) : new Date())" :key="entry.id" 
                             class="bg-primary p-3 rounded border border-accent flex justify-between items-center"
                             :class="{'border-blue-500 ring-1 ring-blue-500': editingEntryId === entry.id}">
                            <div>
                                <div class="font-bold text-sm">{{ entry.item.title }}</div>
                                <div class="text-xs text-text-secondary">{{ formatTime(entry.minutes) }}</div>
                            </div>
                            <div class="flex space-x-2">
                                <button @click="editEntry(entry)" class="text-blue-400 hover:text-blue-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button @click="deleteEntry(entry.id)" class="text-red-400 hover:text-red-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="getEntriesForDate(selectedDate ? new Date(selectedDate) : new Date()).length === 0" class="text-text-secondary text-sm italic">
                            Nenhum apontamento para este dia.
                        </div>
                    </div>
                </div>

                <!-- Right Column: Form -->
                <div class="md:w-1/2">
                    <h3 class="text-xl font-bold mb-4">{{ isEditing ? 'Editar Apontamento' : 'Novo Apontamento' }}</h3>
                    <form @submit.prevent="submit">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Card / Tarefa</label>
                                <Multiselect
                                    v-model="form.item_id"
                                    :options="items.map(i => i.id)"
                                    :custom-label="opt => items.find(i => i.id === opt).title"
                                    placeholder="Selecione um card"
                                    class="w-full"
                                    :disabled="isEditing"
                                />
                                <div v-if="form.errors.item_id" class="text-red-500 text-sm mt-1">{{ form.errors.item_id }}</div>
                                <p v-if="isEditing" class="text-xs text-text-secondary mt-1">O card não pode ser alterado na edição.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Horas</label>
                                    <input type="number" v-model="form.hours" min="0" max="23" class="w-full rounded bg-primary border-accent text-text-primary">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Minutos</label>
                                    <input type="number" v-model="form.minutes" min="0" max="59" class="w-full rounded bg-primary border-accent text-text-primary">
                                </div>
                            </div>
                             <div v-if="form.errors.minutes" class="text-red-500 text-sm mt-1">{{ form.errors.minutes }}</div>
                             <div v-if="form.errors.time" class="text-red-500 text-sm mt-1">{{ form.errors.time }}</div>

                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button v-if="isEditing" type="button" @click="cancelEdit" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancelar Edição</button>
                            <button v-else type="button" @click="showModal = false" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Fechar</button>
                            
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" :disabled="form.processing">
                                {{ isEditing ? 'Atualizar' : 'Salvar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
