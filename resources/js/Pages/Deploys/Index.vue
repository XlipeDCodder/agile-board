<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    deployments: Array,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const isAdmin = computed(() => page.props.auth?.user?.is_admin);
const flashSuccess = computed(() => page.props.flash?.success);

// Filtra deploys por coluna do board.
// 'Aprovados' só mostra staging approved que AINDA não tem prod linkado
// (pra não ficar duplicado na visão).
const stagingApprovedIdsWithProd = computed(() => {
    return new Set(
        props.deployments
            .filter(d => d.environment === 'production' && d.linked_deployment_id)
            .map(d => d.linked_deployment_id)
    );
});

const pendingStaging = computed(() =>
    props.deployments.filter(d => d.environment === 'staging' && d.status === 'pending')
);
const approvedStaging = computed(() =>
    props.deployments.filter(d => d.environment === 'staging' && d.status === 'approved'
        && !stagingApprovedIdsWithProd.value.has(d.id))
);
const completedProduction = computed(() =>
    props.deployments.filter(d => d.environment === 'production' && d.status === 'completed')
);
const rejectedStaging = computed(() =>
    props.deployments.filter(d => d.environment === 'staging' && d.status === 'rejected')
);

const showRejected = ref(false);
const selected = ref(null);
const showDetail = ref(false);
const showReject = ref(false);
const showPromote = ref(false);

const rejectForm = useForm({ reason: '' });
const promoteForm = useForm({
    item_id: null,
    environment: 'production',
    linked_deployment_id: null,
    notes: '',
    is_urgent: false,
});

const openDetail = (d) => {
    selected.value = d;
    showDetail.value = true;
};

const openReject = (d) => {
    selected.value = d;
    rejectForm.reset();
    showReject.value = true;
};

const openPromote = (d) => {
    selected.value = d;
    promoteForm.reset();
    promoteForm.item_id = d.item.id;
    promoteForm.linked_deployment_id = d.id;
    promoteForm.environment = 'production';
    promoteForm.is_urgent = false;
    showPromote.value = true;
};

const approve = (d) => {
    if (!confirm(`Aprovar o deploy de homologação do card #${d.item.id}? Os responsáveis serão notificados.`)) return;
    router.post(route('deploys.approve', d.id), {}, { preserveScroll: true });
};

const submitReject = () => {
    rejectForm.post(route('deploys.reject', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => { showReject.value = false; },
    });
};

const submitPromote = () => {
    promoteForm.post(route('deploys.store'), {
        preserveScroll: true,
        onSuccess: () => { showPromote.value = false; },
    });
};

const formatDate = (iso) => {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString('pt-BR'); }
    catch (e) { return ''; }
};

// Real-time: outros usuários aprovando/rejeitando/promovendo deploys
// atualizam a página automaticamente. Reusa o evento .item.updated
// (já disparado pelo DeploymentController em todas as ações).
// Debounce de 500ms pra não recarregar 5x se houver burst de eventos.
let reloadTimer = null;
const debouncedReload = () => {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
        router.reload({ only: ['deployments'], preserveScroll: true, preserveState: true });
    }, 500);
};

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('board').listen('.item.updated', debouncedReload);
    }
});

onUnmounted(() => {
    if (window.Echo) {
        try { window.Echo.leave('board'); } catch (e) { /* ignore */ }
    }
    clearTimeout(reloadTimer);
});
</script>

<template>
    <Head title="Deploys" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-4xl text-text-main leading-tight inline-flex items-center gap-3"><Icon name="deploys" :size="32" /> Deploys</h2>
        </template>

        <div v-if="flashSuccess" class="mx-6 mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-700">
            {{ flashSuccess }}
        </div>

        <div class="p-6">
            <!-- Board com 3 colunas principais + toggle pra rejeitados -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Pendente aprovação -->
                <div class="bg-surface-variant border border-border-main rounded-2xl p-4 shadow-sm">
                    <h3 class="font-bold text-text-main mb-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 text-amber-500"><Icon name="hourglass" :size="18" /> Em HML aguardando aprovação</span>
                        <span class="text-xs bg-amber-500/20 text-amber-600 px-2 py-0.5 rounded-full">{{ pendingStaging.length }}</span>
                    </h3>
                    <div class="space-y-3">
                        <div v-for="d in pendingStaging" :key="d.id"
                             class="bg-surface border border-border-main rounded-xl p-3 hover:border-brand cursor-pointer transition"
                             @click="openDetail(d)">
                            <div class="text-xs text-text-muted">{{ d.item?.project_name }}</div>
                            <div class="font-bold text-text-main text-sm mt-1">#{{ d.item?.id }} {{ d.item?.title }}</div>
                            <div class="text-xs text-text-muted mt-2">por {{ d.deployer?.name }} · {{ formatDate(d.created_at) }}</div>
                            <p v-if="d.notes" class="text-xs text-text-main mt-2 line-clamp-2 italic">{{ d.notes }}</p>
                            <div v-if="isAdmin && d.deployer?.id !== currentUserId" class="flex gap-2 mt-3" @click.stop>
                                <button @click="approve(d)" class="flex-1 inline-flex items-center justify-center gap-1 text-xs px-2 py-1 rounded bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 font-bold">
                                    <Icon name="check" :size="14" /> Aprovar
                                </button>
                                <button @click="openReject(d)" class="flex-1 inline-flex items-center justify-center gap-1 text-xs px-2 py-1 rounded bg-trello-red/10 text-trello-red hover:bg-trello-red/20 font-bold">
                                    <Icon name="x" :size="14" /> Rejeitar
                                </button>
                            </div>
                            <div v-else-if="isAdmin && d.deployer?.id === currentUserId" class="text-xs text-text-muted mt-3 italic">
                                Você não pode aprovar seu próprio deploy.
                            </div>
                        </div>
                        <div v-if="pendingStaging.length === 0" class="text-xs text-text-muted text-center py-6">
                            Nenhum deploy pendente.
                        </div>
                    </div>
                </div>

                <!-- Aprovados, prontos pra produção -->
                <div class="bg-surface-variant border border-border-main rounded-2xl p-4 shadow-sm">
                    <h3 class="font-bold text-text-main mb-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 text-emerald-600"><Icon name="check" :size="18" /> Aprovados — prontos pra prod</span>
                        <span class="text-xs bg-emerald-500/20 text-emerald-600 px-2 py-0.5 rounded-full">{{ approvedStaging.length }}</span>
                    </h3>
                    <div class="space-y-3">
                        <div v-for="d in approvedStaging" :key="d.id"
                             class="bg-surface border border-border-main rounded-xl p-3 hover:border-brand cursor-pointer transition"
                             @click="openDetail(d)">
                            <div class="text-xs text-text-muted">{{ d.item?.project_name }}</div>
                            <div class="font-bold text-text-main text-sm mt-1">#{{ d.item?.id }} {{ d.item?.title }}</div>
                            <div class="text-xs text-text-muted mt-2">
                                aprovado por {{ d.approver?.name }} · {{ formatDate(d.approved_at) }}
                            </div>
                            <button @click.stop="openPromote(d)"
                                    class="w-full mt-3 inline-flex items-center justify-center gap-1.5 text-xs px-2 py-1.5 rounded bg-brand/10 text-brand hover:bg-brand/20 font-bold">
                                <Icon name="deploys" :size="14" /> Registrar deploy em produção
                            </button>
                        </div>
                        <div v-if="approvedStaging.length === 0" class="text-xs text-text-muted text-center py-6">
                            Nada aprovado aguardando promoção.
                        </div>
                    </div>
                </div>

                <!-- Em produção -->
                <div class="bg-surface-variant border border-border-main rounded-2xl p-4 shadow-sm">
                    <h3 class="font-bold text-text-main mb-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 text-brand"><Icon name="deploys" :size="18" /> Em produção</span>
                        <span class="text-xs bg-brand/20 text-brand px-2 py-0.5 rounded-full">{{ completedProduction.length }}</span>
                    </h3>
                    <div class="space-y-3 max-h-[600px] overflow-y-auto">
                        <div v-for="d in completedProduction" :key="d.id"
                             class="bg-surface border border-border-main rounded-xl p-3 hover:border-brand cursor-pointer transition"
                             @click="openDetail(d)">
                            <div class="text-xs text-text-muted">{{ d.item?.project_name }}</div>
                            <div class="font-bold text-text-main text-sm mt-1">#{{ d.item?.id }} {{ d.item?.title }}</div>
                            <div class="text-xs text-text-muted mt-2">
                                por {{ d.deployer?.name }} · {{ formatDate(d.created_at) }}
                            </div>
                            <span v-if="d.is_urgent" class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded bg-trello-red/10 text-trello-red font-bold mt-2">
                                <Icon name="warning" :size="13" /> Urgente (pulou homologação)
                            </span>
                        </div>
                        <div v-if="completedProduction.length === 0" class="text-xs text-text-muted text-center py-6">
                            Nenhum deploy em produção ainda.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejeitados (collapsado) -->
            <div class="mt-6 bg-surface-variant border border-border-main rounded-2xl shadow-sm">
                <button @click="showRejected = !showRejected"
                        class="w-full px-4 py-3 text-left flex items-center justify-between hover:bg-surface transition">
                    <h3 class="font-bold text-text-main flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 text-trello-red"><Icon name="circle-x" :size="18" /> Rejeitados</span>
                        <span class="text-xs bg-trello-red/20 text-trello-red px-2 py-0.5 rounded-full">{{ rejectedStaging.length }}</span>
                    </h3>
                    <span class="text-text-muted text-sm">{{ showRejected ? '▲' : '▼' }}</span>
                </button>
                <div v-if="showRejected" class="px-4 pb-4 space-y-2">
                    <div v-for="d in rejectedStaging" :key="d.id"
                         class="bg-surface border border-border-main rounded-xl p-3 cursor-pointer hover:border-brand transition"
                         @click="openDetail(d)">
                        <div class="font-bold text-text-main text-sm">#{{ d.item?.id }} {{ d.item?.title }}</div>
                        <div class="text-xs text-text-muted mt-1">
                            rejeitado por {{ d.approver?.name }} · {{ formatDate(d.rejected_at) }}
                        </div>
                        <div class="text-xs text-trello-red mt-2 italic">{{ d.rejection_reason }}</div>
                    </div>
                    <div v-if="rejectedStaging.length === 0" class="text-xs text-text-muted text-center py-4">
                        Nenhum deploy rejeitado.
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal detalhe -->
        <Modal :show="showDetail" @close="showDetail = false" maxWidth="lg">
            <div v-if="selected" class="p-6 bg-surface-variant space-y-4">
                <h3 class="font-bold text-lg text-text-main">
                    Deploy #{{ selected.id }} —
                    <span :class="selected.environment === 'production' ? 'text-brand' : 'text-amber-600'">
                        {{ selected.environment === 'production' ? '🚀 Produção' : '🟡 Homologação' }}
                    </span>
                </h3>
                <div class="space-y-1 text-sm">
                    <p><strong>Card:</strong> #{{ selected.item?.id }} {{ selected.item?.title }}</p>
                    <p><strong>Projeto:</strong> {{ selected.item?.project_name }}</p>
                    <p><strong>Deployer:</strong> {{ selected.deployer?.name }}</p>
                    <p><strong>Criado em:</strong> {{ formatDate(selected.created_at) }}</p>
                    <p><strong>Status:</strong> {{ selected.status }}</p>
                    <p v-if="selected.is_urgent" class="text-trello-red font-bold">⚠️ Deploy urgente (pulou homologação)</p>
                    <p v-if="selected.approver"><strong>Aprovador:</strong> {{ selected.approver.name }} ({{ formatDate(selected.approved_at) }})</p>
                    <p v-if="selected.rejection_reason" class="text-trello-red"><strong>Motivo da rejeição:</strong> {{ selected.rejection_reason }}</p>
                </div>
                <div v-if="selected.notes" class="bg-surface border border-border-main rounded-lg p-3 text-sm">
                    <p class="font-bold text-text-main mb-1">Notas:</p>
                    <p class="text-text-muted whitespace-pre-wrap">{{ selected.notes }}</p>
                </div>
                <div class="flex justify-end">
                    <button @click="showDetail = false" class="btn-secondary">Fechar</button>
                </div>
            </div>
        </Modal>

        <!-- Modal rejeitar -->
        <Modal :show="showReject" @close="showReject = false" maxWidth="md">
            <form v-if="selected" @submit.prevent="submitReject" class="p-6 bg-surface-variant space-y-4">
                <h3 class="font-bold text-lg text-text-main">Rejeitar deploy do card #{{ selected.item?.id }}</h3>
                <p class="text-sm text-text-muted">O motivo será enviado pro deployer junto com a notificação.</p>
                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Motivo da rejeição</label>
                    <textarea v-model="rejectForm.reason" rows="4" class="input-field w-full" required minlength="5" maxlength="1000"></textarea>
                    <div v-if="rejectForm.errors.reason" class="text-trello-red text-xs mt-1">{{ rejectForm.errors.reason }}</div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showReject = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" :disabled="rejectForm.processing"
                            class="px-4 py-2 rounded-lg bg-trello-red text-white font-bold hover:opacity-90 disabled:opacity-50">
                        Rejeitar
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal promover pra produção -->
        <Modal :show="showPromote" @close="showPromote = false" maxWidth="md">
            <form v-if="selected" @submit.prevent="submitPromote" class="p-6 bg-surface-variant space-y-4">
                <h3 class="font-bold text-lg text-text-main">Registrar deploy em produção</h3>
                <p class="text-sm text-text-muted">
                    Card <strong>#{{ selected.item?.id }} {{ selected.item?.title }}</strong>, vinculado ao deploy de homologação #{{ selected.id }}.
                </p>
                <div>
                    <label class="block text-sm font-bold text-text-main mb-1">Notas (opcional)</label>
                    <textarea v-model="promoteForm.notes" rows="3" class="input-field w-full" maxlength="2000"
                              placeholder="Versão deployada, observações, link da pipeline, etc."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showPromote = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" :disabled="promoteForm.processing"
                            class="px-4 py-2 rounded-lg bg-brand text-white font-bold hover:opacity-90 disabled:opacity-50">
                        Registrar produção
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
