<script setup>
import { ref } from 'vue';
import axios from 'axios';
import MarkdownViewer from '@/Components/MarkdownViewer.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Escreva em Markdown…' },
    rows: { type: Number, default: 4 },
});
const emit = defineEmits(['update:modelValue']);

const activeTab = ref('write'); // 'write' | 'preview'
const textareaRef = ref(null);
const fileInputRef = ref(null);
const uploading = ref(false);
const uploadError = ref(null);

const update = (val) => emit('update:modelValue', val);

// Insere texto na posição do cursor do textarea, mantendo seleção/scroll.
// Se houver texto selecionado e `wrap` for true, envolve a seleção (ex: **sel**).
const insertAtCursor = (before, after = '', placeholder = '') => {
    const el = textareaRef.value;
    if (!el) {
        update((props.modelValue || '') + before + placeholder + after);
        return;
    }
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const value = props.modelValue || '';
    const selected = value.substring(start, end) || placeholder;
    const newValue = value.substring(0, start) + before + selected + after + value.substring(end);
    update(newValue);
    // Reposiciona o cursor depois do conteúdo inserido.
    requestAnimationFrame(() => {
        el.focus();
        const pos = start + before.length + selected.length + after.length;
        el.setSelectionRange(pos, pos);
    });
};

const toolbar = [
    { label: 'B', title: 'Negrito', action: () => insertAtCursor('**', '**', 'negrito') },
    { label: 'I', title: 'Itálico', action: () => insertAtCursor('*', '*', 'itálico'), italic: true },
    { label: 'H', title: 'Título', action: () => insertAtCursor('## ', '', 'Título') },
    { label: '•', title: 'Lista', action: () => insertAtCursor('- ', '', 'item') },
    { label: '<>', title: 'Código', action: () => insertAtCursor('`', '`', 'código') },
    { label: '🔗', title: 'Link', action: () => insertAtCursor('[', '](https://)', 'texto') },
];

const uploadImage = async (file) => {
    uploadError.value = null;
    const placeholder = `![enviando ${file.name || 'imagem'}…]()`;
    // Insere placeholder enquanto sobe.
    insertAtCursor(placeholder);
    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('image', file);
        const { data } = await axios.post(route('uploads.inline-image'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        // Substitui o placeholder pela URL final.
        const finalMd = `![imagem](${data.url})`;
        update((props.modelValue || '').replace(placeholder, finalMd));
    } catch (err) {
        update((props.modelValue || '').replace(placeholder, ''));
        uploadError.value = err.response?.status === 422
            ? 'Formato de imagem não suportado (use PNG, JPG, GIF ou WEBP).'
            : 'Falha ao enviar a imagem. Tente novamente.';
    } finally {
        uploading.value = false;
    }
};

const onPaste = (e) => {
    const items = e.clipboardData?.items;
    if (!items) return;
    for (const item of items) {
        if (item.type && item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if (file) {
                e.preventDefault();
                uploadImage(file);
                return;
            }
        }
    }
};

const onDrop = (e) => {
    const files = e.dataTransfer?.files;
    if (!files || files.length === 0) return;
    const imageFiles = Array.from(files).filter(f => f.type.startsWith('image/'));
    if (imageFiles.length > 0) {
        e.preventDefault();
        imageFiles.forEach(uploadImage);
    }
};

const onFilePick = (e) => {
    const files = e.target.files;
    if (files) Array.from(files).forEach(uploadImage);
    e.target.value = ''; // permite re-selecionar o mesmo arquivo
};
</script>

<template>
    <div class="border border-border-main rounded-lg overflow-hidden bg-surface">
        <!-- Cabeçalho: abas + toolbar -->
        <div class="flex items-center justify-between border-b border-border-main bg-surface-variant px-2 py-1.5">
            <div class="flex gap-1">
                <button type="button" @click="activeTab = 'write'"
                    :class="['px-3 py-1 text-xs font-medium rounded transition',
                        activeTab === 'write' ? 'bg-brand text-white' : 'text-text-muted hover:text-text-main']">
                    Escrever
                </button>
                <button type="button" @click="activeTab = 'preview'"
                    :class="['px-3 py-1 text-xs font-medium rounded transition',
                        activeTab === 'preview' ? 'bg-brand text-white' : 'text-text-muted hover:text-text-main']">
                    Pré-visualizar
                </button>
            </div>
            <div v-if="activeTab === 'write'" class="flex items-center gap-0.5">
                <button v-for="btn in toolbar" :key="btn.label" type="button"
                    @click="btn.action" :title="btn.title"
                    :class="['w-7 h-7 rounded text-xs font-bold text-text-muted hover:bg-surface-hover hover:text-text-main transition',
                        btn.italic ? 'italic' : '']">
                    {{ btn.label }}
                </button>
                <button type="button" @click="fileInputRef?.click()" title="Inserir imagem"
                    class="w-7 h-7 rounded text-sm text-text-muted hover:bg-surface-hover hover:text-text-main transition">
                    🖼️
                </button>
                <input ref="fileInputRef" type="file" accept="image/png,image/jpeg,image/gif,image/webp"
                    multiple class="hidden" @change="onFilePick">
            </div>
        </div>

        <!-- Corpo -->
        <div v-show="activeTab === 'write'">
            <textarea ref="textareaRef"
                :value="modelValue"
                @input="update($event.target.value)"
                @paste="onPaste"
                @drop="onDrop"
                @dragover.prevent
                :rows="rows"
                :placeholder="placeholder"
                class="w-full bg-transparent border-0 focus:ring-0 text-sm text-text-main resize-y px-3 py-2"></textarea>
        </div>
        <div v-show="activeTab === 'preview'" class="px-3 py-2 min-h-[5rem] text-sm text-text-main">
            <MarkdownViewer v-if="modelValue" :source="modelValue" />
            <p v-else class="text-text-muted italic">Nada pra pré-visualizar ainda.</p>
        </div>

        <!-- Rodapé de status -->
        <div v-if="uploading || uploadError" class="px-3 py-1.5 border-t border-border-main text-xs">
            <span v-if="uploading" class="text-text-muted">⏳ Enviando imagem…</span>
            <span v-else-if="uploadError" class="text-trello-red">{{ uploadError }}</span>
        </div>
        <div v-else class="px-3 py-1 border-t border-border-main text-[10px] text-text-muted">
            Markdown suportado · cole ou arraste imagens
        </div>
    </div>
</template>
