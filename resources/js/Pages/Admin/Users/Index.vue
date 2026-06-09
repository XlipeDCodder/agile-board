<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    users: Array,
    registrationEnabled: Boolean,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    is_admin: false,
});

const flashTempPassword = computed(() => page.props.flash?.temp_password || null);
const flashSuccess = computed(() => page.props.flash?.success || null);

const formatDate = (iso) => {
    if (!iso) return '';
    try { return new Date(iso).toLocaleDateString('pt-BR'); }
    catch (e) { return ''; }
};

const openCreate = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (user) => {
    isEditing.value = true;
    editingId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.is_admin = user.is_admin;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.users.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
};

const destroy = (user) => {
    if (!confirm(`Excluir ${user.name}? Os registros históricos dele continuam preservados — só o acesso é bloqueado.`)) return;
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
};

const restore = (user) => {
    router.post(route('admin.users.restore', user.id), {}, { preserveScroll: true });
};

const resetPassword = (user) => {
    if (!confirm(`Gerar nova senha temporária para ${user.name}? A senha antiga deixará de funcionar imediatamente.`)) return;
    router.post(route('admin.users.reset-password', user.id), {}, { preserveScroll: true });
};

const toggleRegistration = () => {
    const newValue = !props.registrationEnabled;
    const msg = newValue
        ? 'Habilitar o cadastro público? Qualquer pessoa poderá criar conta pela página inicial.'
        : 'Desabilitar o cadastro público? Apenas você (admin) poderá criar novos usuários.';
    if (!confirm(msg)) return;
    router.post(route('admin.settings.registration-toggle'), { enabled: newValue }, { preserveScroll: true });
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
};

const isSelf = (user) => user.id === currentUserId.value;
</script>

<template>
    <Head title="Usuários" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-4xl text-text-main leading-tight inline-flex items-center gap-3"><Icon name="users" :size="32" /> Usuários</h2>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6">
            <!-- Feedback de senha temporária -->
            <div v-if="flashTempPassword" class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-sm text-text-main">
                <p class="font-bold mb-2 inline-flex items-center gap-1.5"><Icon name="key" :size="16" /> Senha temporária gerada</p>
                <p class="text-text-muted mb-2">Anote ou copie esta senha agora — ela <strong>não será exibida novamente</strong>. O usuário será obrigado a trocar no primeiro login.</p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 px-3 py-2 rounded-lg bg-surface border border-border-main font-mono text-base select-all">{{ flashTempPassword }}</code>
                    <button @click="copyToClipboard(flashTempPassword)" class="btn-secondary text-sm">Copiar</button>
                </div>
            </div>

            <div v-if="flashSuccess && !flashTempPassword" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>

            <!-- Toggle de cadastro público -->
            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-lg text-text-main flex items-center gap-2">
                            <span>🚪</span>
                            <span>Cadastro público</span>
                        </h3>
                        <p class="text-text-muted text-sm mt-1">
                            Quando habilitado, qualquer pessoa com o link da página inicial pode criar uma conta. Quando desabilitado, somente você (admin) cadastra usuários — a página de registro fica bloqueada mesmo via URL direta.
                        </p>
                        <p class="text-sm mt-2">
                            Status atual: <strong :class="['inline-flex items-center gap-1', registrationEnabled ? 'text-emerald-500' : 'text-trello-red']">
                                <Icon :name="registrationEnabled ? 'check' : 'key'" :size="14" />
                                {{ registrationEnabled ? 'Habilitado' : 'Desabilitado' }}
                            </strong>
                        </p>
                    </div>
                    <button @click="toggleRegistration"
                            class="px-4 py-2 rounded-xl border-2 font-bold text-sm whitespace-nowrap"
                            :class="registrationEnabled
                                ? 'bg-trello-red/10 text-trello-red border-trello-red/40 hover:bg-trello-red/20'
                                : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/40 hover:bg-emerald-500/20'">
                        {{ registrationEnabled ? 'Desabilitar' : 'Habilitar' }}
                    </button>
                </div>
            </div>

            <!-- Tabela de usuários -->
            <div class="bg-surface-variant border border-border-main rounded-2xl shadow-sm overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b border-border-main">
                    <h3 class="font-bold text-lg text-text-main">Lista de usuários</h3>
                    <button @click="openCreate" class="btn-primary">+ Cadastrar usuário</button>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-surface">
                        <tr class="text-left text-text-muted text-xs uppercase">
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Papel</th>
                            <th class="px-4 py-3">Criado</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" class="border-t border-border-main">
                            <td class="px-4 py-3 font-bold text-text-main">
                                {{ user.name }}
                                <span v-if="isSelf(user)" class="ml-1 text-xs text-text-muted">(você)</span>
                            </td>
                            <td class="px-4 py-3 text-text-muted">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.is_admin" class="px-2 py-0.5 rounded text-xs font-bold bg-brand/10 text-brand">Admin</span>
                                <span v-else class="px-2 py-0.5 rounded text-xs bg-surface text-text-muted">Membro</span>
                            </td>
                            <td class="px-4 py-3 text-text-muted text-xs">{{ formatDate(user.created_at) }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.deleted_at" class="inline-flex items-center gap-1 text-xs font-bold text-trello-red"><Icon name="trash" :size="13" /> Excluído</span>
                                <span v-else-if="user.must_change_password" class="inline-flex items-center gap-1 text-xs font-bold text-amber-600"><Icon name="key" :size="13" /> Pendente troca</span>
                                <span v-else class="inline-flex items-center gap-1 text-xs text-emerald-500"><Icon name="check" :size="13" /> Ativo</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <template v-if="user.deleted_at">
                                        <button @click="restore(user)" class="text-xs text-brand hover:underline">Restaurar</button>
                                    </template>
                                    <template v-else>
                                        <button v-if="!isSelf(user)" @click="resetPassword(user)" class="text-xs text-amber-600 hover:underline">Resetar senha</button>
                                        <button v-if="!isSelf(user)" @click="openEdit(user)" class="text-xs text-brand hover:underline">Editar</button>
                                        <button v-if="!isSelf(user)" @click="destroy(user)" class="text-xs text-trello-red hover:underline">Excluir</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-text-muted">Nenhum usuário cadastrado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal CRUD -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="md">
            <form @submit.prevent="submit" class="p-6 bg-surface-variant space-y-4">
                <h3 class="font-bold text-lg text-text-main">{{ isEditing ? 'Editar usuário' : 'Cadastrar usuário' }}</h3>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Nome</label>
                    <input v-model="form.name" type="text" class="input-field w-full" required maxlength="120" />
                    <div v-if="form.errors.name" class="text-trello-red text-xs mt-1">{{ form.errors.name }}</div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Email</label>
                    <input v-model="form.email" type="email" class="input-field w-full" required maxlength="191" />
                    <div v-if="form.errors.email" class="text-trello-red text-xs mt-1">{{ form.errors.email }}</div>
                </div>

                <div v-if="!isEditing">
                    <label class="block text-sm font-bold text-text-main mb-1">Senha temporária (opcional)</label>
                    <input v-model="form.password" type="text" class="input-field w-full" placeholder="Deixe em branco para gerar automaticamente" maxlength="128" />
                    <p class="text-xs text-text-muted mt-1">O usuário será obrigado a trocar no primeiro login, independente do valor.</p>
                    <div v-if="form.errors.password" class="text-trello-red text-xs mt-1">{{ form.errors.password }}</div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_admin" />
                    <span class="text-text-main">Conceder privilégios de administrador</span>
                </label>
                <div v-if="form.errors.is_admin" class="text-trello-red text-xs">{{ form.errors.is_admin }}</div>

                <div class="flex justify-end gap-3 pt-4 border-t border-border-main">
                    <button type="button" @click="showModal = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando…' : (isEditing ? 'Atualizar' : 'Cadastrar') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
