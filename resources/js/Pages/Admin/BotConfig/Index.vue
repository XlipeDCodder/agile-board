<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    config: Object,
    googleConnection: Object,
    googleOAuthConfigured: Boolean,
    googleAllowedDomain: String,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.google_success || null);
const flashError = computed(() => page.props.flash?.google_error || null);

const disconnectGoogle = () => {
    if (!confirm('Desconectar sua conta Google? O Icarus não conseguirá mais gerar Docs/Sheets até você reconectar.')) return;
    router.post(route('admin.google.disconnect'));
};

const form = useForm({
    provider: props.config?.provider || 'gemini',
    model: props.config?.model || 'gemini-2.0-flash',
    api_key: '',
});

const testState = ref({ loading: false, ok: null, message: '' });

const test = async () => {
    if (!form.api_key) {
        testState.value = { loading: false, ok: false, message: 'Informe a API key antes de testar.' };
        return;
    }
    testState.value = { loading: true, ok: null, message: '' };
    try {
        const { data } = await axios.post(route('admin.bot-config.test'), {
            provider: form.provider,
            model: form.model,
            api_key: form.api_key,
        });
        testState.value = { loading: false, ok: true, message: `Conexão OK. Amostra: "${data.sample}"` };
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.error || 'Falha na conexão.';
        testState.value = { loading: false, ok: false, message: msg };
    }
};

const save = () => {
    form.put(route('admin.bot-config.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('api_key');
        },
    });
};

const modelSuggestions = {
    gemini: ['gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-1.5-pro'],
};
</script>

<template>
    <Head title="Bot Config" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-4xl text-text-main leading-tight">🤖 Bot Config</h2>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto space-y-6">
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-sm text-text-main">
                <p class="font-bold mb-1">⚠️ Configurações sensíveis</p>
                <p class="text-text-muted">
                    Estas configurações controlam o assistente Icarus. <strong>A chave de API dá acesso pago ao provedor</strong> — não compartilhe. Ao usar o bot, dados internos (nomes, comentários, projetos) serão enviados para o provedor escolhido.
                </p>
            </div>

            <form @submit.prevent="save" class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm space-y-6">
                <div v-if="config" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-text-main">
                    Configuração ativa: <strong>{{ config.provider }}</strong> / <strong>{{ config.model }}</strong> · chave armazenada com segurança ✓
                </div>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-2">Provedor</label>
                    <select v-model="form.provider" class="input-field w-full">
                        <option value="gemini">Google Gemini</option>
                    </select>
                    <p class="text-xs text-text-muted mt-1">Mais provedores em breve.</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-2">Modelo</label>
                    <input v-model="form.model" type="text" list="model-suggestions" class="input-field w-full" required />
                    <datalist id="model-suggestions">
                        <option v-for="m in modelSuggestions[form.provider] || []" :key="m" :value="m" />
                    </datalist>
                    <p class="text-xs text-text-muted mt-1">Sugestões: gemini-2.0-flash, gemini-2.5-flash.</p>
                    <div v-if="form.errors.model" class="text-trello-red text-xs mt-1">{{ form.errors.model }}</div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-text-main mb-2">API Key</label>
                    <input v-model="form.api_key" type="password" autocomplete="off" class="input-field w-full"
                        :placeholder="config ? 'Deixe em branco para manter a atual ou digite uma nova' : 'Cole aqui sua chave'" />
                    <p class="text-xs text-text-muted mt-1">Armazenada criptografada no banco. Nunca aparece em logs nem é retornada ao frontend.</p>
                    <div v-if="form.errors.api_key" class="text-trello-red text-xs mt-1">{{ form.errors.api_key }}</div>
                </div>

                <div v-if="testState.message" :class="[
                    'p-3 rounded-xl text-sm',
                    testState.ok ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-700' : 'bg-trello-red/10 border border-trello-red/30 text-trello-red',
                ]">
                    {{ testState.message }}
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-border-main">
                    <button type="button" @click="test" :disabled="testState.loading" class="btn-secondary">
                        {{ testState.loading ? 'Testando…' : 'Testar conexão' }}
                    </button>
                    <button type="submit" :disabled="form.processing || !form.api_key" class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </form>

            <div class="bg-surface-variant border border-border-main rounded-2xl p-6 shadow-sm space-y-4">
                <div>
                    <h3 class="font-bold text-lg text-text-main flex items-center gap-2">
                        <span>🔗</span>
                        <span>Conexão Google Workspace</span>
                    </h3>
                    <p class="text-text-muted text-sm mt-1">
                        Necessária para que o Icarus possa criar Google Docs e Sheets diretamente no <strong>seu Drive</strong>.
                        <span v-if="googleAllowedDomain">Apenas contas <code>@{{ googleAllowedDomain }}</code> podem conectar.</span>
                    </p>
                </div>

                <div v-if="flashSuccess" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-700">
                    {{ flashSuccess }}
                </div>
                <div v-if="flashError" class="p-3 rounded-xl bg-trello-red/10 border border-trello-red/30 text-sm text-trello-red">
                    {{ flashError }}
                </div>

                <div v-if="!googleOAuthConfigured" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-text-main">
                    OAuth do Google ainda não foi configurado no servidor. Defina <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code> e <code>GOOGLE_REDIRECT_URI</code> no <code>.env</code>.
                </div>

                <div v-else-if="googleConnection" class="flex items-center justify-between gap-4">
                    <div class="text-sm">
                        <p class="text-text-main">
                            ✅ Conectado como <strong>{{ googleConnection.google_email }}</strong>
                        </p>
                        <p class="text-text-muted text-xs mt-1" v-if="googleConnection.expires_at">
                            Sessão atual expira automaticamente; o token é renovado quando necessário.
                        </p>
                    </div>
                    <button type="button" @click="disconnectGoogle" class="btn-secondary">
                        Desconectar
                    </button>
                </div>

                <div v-else>
                    <a :href="route('admin.google.connect')" class="btn-primary inline-block">
                        Conectar Google
                    </a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
