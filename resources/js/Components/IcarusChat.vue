<script setup>
import { ref, nextTick, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    userId: { type: Number, required: true },
    userName: { type: String, required: true },
});

const isOpen = ref(false);
const messages = ref([]);
const draft = ref('');
const isLoading = ref(false);
const error = ref(null);
const showWelcome = ref(true);
const messagesEl = ref(null);

const canSend = computed(() => draft.value.trim().length > 0 && !isLoading.value);

const open = () => {
    isOpen.value = true;
    nextTick(() => scrollToBottom());
};

const closeSession = () => {
    messages.value = [];
    error.value = null;
    showWelcome.value = true;
    isOpen.value = false;
};

const scrollToBottom = () => {
    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
};

const send = async () => {
    const text = draft.value.trim();
    if (!text || isLoading.value) return;

    error.value = null;
    showWelcome.value = false;
    messages.value.push({ role: 'user', content: text, at: new Date() });
    draft.value = '';
    isLoading.value = true;
    await nextTick();
    scrollToBottom();

    try {
        const payload = {
            messages: messages.value.map(m => ({ role: m.role, content: m.content })),
        };
        const { data } = await axios.post(route('admin.icarus.chat', props.userId), payload);
        messages.value.push({ role: 'assistant', content: data.reply, at: new Date() });
    } catch (err) {
        const msg = err.response?.data?.error
            || err.response?.data?.message
            || (err.response?.status === 429 ? 'Muitas requisições. Aguarde alguns segundos.' : 'Falha ao falar com o Icarus.');
        error.value = msg;
    } finally {
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};

const onEnter = (e) => {
    if (e.shiftKey) return;
    e.preventDefault();
    send();
};
</script>

<template>
    <button
        v-if="!isOpen"
        @click="open"
        title="Falar com Icarus"
        class="fixed bottom-6 right-6 z-40 h-14 w-14 rounded-full bg-brand text-white text-2xl shadow-2xl hover:scale-110 hover:shadow-brand/50 transition flex items-center justify-center"
    >
        🤖
    </button>

    <div
        v-if="isOpen"
        class="fixed bottom-6 right-6 z-40 w-[380px] max-w-[calc(100vw-2rem)] h-[560px] max-h-[calc(100vh-3rem)] bg-surface-variant border border-border-main rounded-2xl shadow-2xl flex flex-col overflow-hidden"
    >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-border-main bg-gradient-to-r from-surface to-surface-variant">
            <div class="flex items-center gap-2">
                <div class="h-9 w-9 rounded-full bg-brand text-white text-lg flex items-center justify-center">🤖</div>
                <div>
                    <div class="font-bold text-text-main text-sm">Icarus</div>
                    <div class="text-xs text-text-muted">Analisando: {{ userName }}</div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    @click="closeSession"
                    title="Encerrar sessão e apagar mensagens"
                    class="px-2 py-1 text-xs rounded-md text-text-muted hover:text-trello-red hover:bg-surface-hover transition"
                >
                    Encerrar
                </button>
                <button
                    @click="isOpen = false"
                    title="Minimizar (mantém mensagens)"
                    class="p-1.5 rounded-md text-text-muted hover:text-text-main hover:bg-surface-hover transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13H5" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Welcome -->
        <div v-if="showWelcome" class="p-4 m-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-sm text-text-main">
            <p class="font-bold mb-1">Olá! Sou o Icarus 🤖</p>
            <p class="text-text-muted">
                Vou te ajudar a analisar <strong class="text-text-main">{{ userName }}</strong>. Você pode perguntar sobre cards, tempo médio em colunas, expectativa de entrega, atividades recentes e outras métricas.
            </p>
            <p class="mt-2 text-xs text-text-muted">
                ⚠️ <strong>Esta sessão não tem persistência</strong>: ao clicar "Encerrar" as mensagens são apagadas. Os dados do colaborador são enviados para o provedor de IA configurado.
            </p>
        </div>

        <!-- Messages -->
        <div ref="messagesEl" class="flex-1 overflow-y-auto px-4 py-2 space-y-3">
            <div v-for="(msg, i) in messages" :key="i" :class="[
                'flex',
                msg.role === 'user' ? 'justify-end' : 'justify-start',
            ]">
                <div :class="[
                    'max-w-[85%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words',
                    msg.role === 'user'
                        ? 'bg-brand text-white rounded-br-sm'
                        : 'bg-surface text-text-main border border-border-main rounded-bl-sm',
                ]">
                    {{ msg.content }}
                </div>
            </div>
            <div v-if="isLoading" class="flex justify-start">
                <div class="bg-surface text-text-muted border border-border-main rounded-2xl rounded-bl-sm px-3 py-2 text-sm">
                    <span class="inline-flex gap-1">
                        <span class="h-2 w-2 rounded-full bg-text-muted animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="h-2 w-2 rounded-full bg-text-muted animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="h-2 w-2 rounded-full bg-text-muted animate-bounce" style="animation-delay: 300ms"></span>
                    </span>
                </div>
            </div>
            <div v-if="error" class="text-sm p-3 rounded-xl bg-trello-red/10 text-trello-red border border-trello-red/30">
                {{ error }}
            </div>
        </div>

        <!-- Input -->
        <div class="p-3 border-t border-border-main bg-surface">
            <div class="flex items-end gap-2">
                <textarea
                    v-model="draft"
                    @keydown.enter="onEnter"
                    rows="2"
                    placeholder="Pergunte algo sobre o colaborador..."
                    class="flex-1 input-field resize-none text-sm"
                    :disabled="isLoading"
                ></textarea>
                <button
                    @click="send"
                    :disabled="!canSend"
                    class="btn-primary px-3 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
