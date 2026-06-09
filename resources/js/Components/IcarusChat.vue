<script setup>
import { ref, nextTick, computed } from 'vue';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import Icon from '@/Components/Icon.vue';

marked.setOptions({ breaks: true, gfm: true });

// Hook do DOMPurify: links que apontam pra fora abrem em nova aba e ganham
// rel="noopener noreferrer". Links pra docs.google.com (saída de tools)
// recebem um ícone discreto pra deixar visualmente claro que é arquivo.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName !== 'A') return;
    const href = node.getAttribute('href') || '';
    if (!/^https?:/i.test(href)) return;
    node.setAttribute('target', '_blank');
    node.setAttribute('rel', 'noopener noreferrer');
    if (href.includes('docs.google.com/document/')) {
        node.textContent = '📄 ' + node.textContent;
    } else if (href.includes('docs.google.com/spreadsheets/')) {
        node.textContent = '📊 ' + node.textContent;
    }
});

const renderMarkdown = (text) => {
    if (!text) return '';
    const raw = marked.parse(text);
    return DOMPurify.sanitize(raw, { ADD_ATTR: ['target', 'rel'] });
};

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
        class="fixed bottom-6 right-6 z-40 h-14 w-14 rounded-full bg-brand text-white shadow-2xl hover:scale-110 hover:shadow-brand/50 transition flex items-center justify-center"
    >
        <Icon name="bot" :size="26" />
    </button>

    <div
        v-if="isOpen"
        class="fixed bottom-6 right-6 z-40 w-[380px] max-w-[calc(100vw-2rem)] h-[560px] max-h-[calc(100vh-3rem)] bg-surface-variant border border-border-main rounded-2xl shadow-2xl flex flex-col overflow-hidden"
    >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-border-main bg-gradient-to-r from-surface to-surface-variant">
            <div class="flex items-center gap-2">
                <div class="h-9 w-9 rounded-full bg-brand text-white flex items-center justify-center"><Icon name="bot" :size="18" /></div>
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
                    <Icon name="minus" :size="16" />
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
                <div v-if="msg.role === 'user'"
                    class="max-w-[85%] px-3 py-2 rounded-2xl rounded-br-sm text-sm whitespace-pre-wrap break-words bg-brand text-white">
                    {{ msg.content }}
                </div>
                <div v-else
                    class="max-w-[85%] px-3 py-2 rounded-2xl rounded-bl-sm text-sm break-words bg-surface text-text-main border border-border-main icarus-markdown"
                    v-html="renderMarkdown(msg.content)">
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
                    <Icon name="send" :size="20" />
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.icarus-markdown :deep(p) { margin: 0 0 0.5rem 0; }
.icarus-markdown :deep(p:last-child) { margin-bottom: 0; }
.icarus-markdown :deep(strong) { font-weight: 700; }
.icarus-markdown :deep(em) { font-style: italic; }
.icarus-markdown :deep(ul),
.icarus-markdown :deep(ol) { margin: 0.25rem 0 0.5rem 1.25rem; padding: 0; }
.icarus-markdown :deep(ul) { list-style: disc; }
.icarus-markdown :deep(ol) { list-style: decimal; }
.icarus-markdown :deep(li) { margin: 0.125rem 0; }
.icarus-markdown :deep(li > p) { margin: 0; }
.icarus-markdown :deep(code) {
    background: rgb(var(--color-surface-variant) / 0.6);
    padding: 0.1rem 0.35rem;
    border-radius: 0.3rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.85em;
}
.icarus-markdown :deep(pre) {
    background: rgb(var(--color-surface-variant) / 0.6);
    padding: 0.6rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.4rem 0;
}
.icarus-markdown :deep(pre code) { background: transparent; padding: 0; }
.icarus-markdown :deep(blockquote) {
    border-left: 3px solid rgb(var(--color-brand));
    padding-left: 0.6rem;
    margin: 0.4rem 0;
    color: rgb(var(--color-text-muted));
}
.icarus-markdown :deep(a) {
    color: rgb(var(--color-brand));
    text-decoration: underline;
}
.icarus-markdown :deep(table) {
    border-collapse: collapse;
    margin: 0.4rem 0;
}
.icarus-markdown :deep(th),
.icarus-markdown :deep(td) {
    border: 1px solid rgb(var(--color-border-main));
    padding: 0.25rem 0.5rem;
}
.icarus-markdown :deep(h1),
.icarus-markdown :deep(h2),
.icarus-markdown :deep(h3) {
    font-weight: 700;
    margin: 0.5rem 0 0.25rem 0;
}
.icarus-markdown :deep(h1) { font-size: 1.1em; }
.icarus-markdown :deep(h2) { font-size: 1.05em; }
.icarus-markdown :deep(h3) { font-size: 1em; }
</style>
