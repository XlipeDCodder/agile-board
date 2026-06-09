<script setup>
import { computed } from 'vue';
import { renderMarkdown } from '@/composables/useMarkdown';

const props = defineProps({
    source: { type: String, default: '' },
});

const html = computed(() => renderMarkdown(props.source));
</script>

<template>
    <div class="markdown-body" v-html="html"></div>
</template>

<style scoped>
.markdown-body :deep(p) { margin: 0 0 0.5rem 0; }
.markdown-body :deep(p:last-child) { margin-bottom: 0; }
.markdown-body :deep(strong) { font-weight: 700; }
.markdown-body :deep(em) { font-style: italic; }
.markdown-body :deep(ul),
.markdown-body :deep(ol) { margin: 0.25rem 0 0.5rem 1.25rem; padding: 0; }
.markdown-body :deep(ul) { list-style: disc; }
.markdown-body :deep(ol) { list-style: decimal; }
.markdown-body :deep(li) { margin: 0.125rem 0; }
.markdown-body :deep(li > p) { margin: 0; }
.markdown-body :deep(code) {
    background: rgb(var(--color-surface-variant) / 0.6);
    padding: 0.1rem 0.35rem;
    border-radius: 0.3rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.85em;
}
.markdown-body :deep(pre) {
    background: rgb(var(--color-surface-variant) / 0.6);
    padding: 0.6rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.4rem 0;
}
.markdown-body :deep(pre code) { background: transparent; padding: 0; }
.markdown-body :deep(blockquote) {
    border-left: 3px solid rgb(var(--color-brand));
    padding-left: 0.6rem;
    margin: 0.4rem 0;
    color: rgb(var(--color-text-muted));
}
.markdown-body :deep(a) {
    color: rgb(var(--color-brand));
    text-decoration: underline;
}
.markdown-body :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 0.4rem 0;
    border: 1px solid rgb(var(--color-border-main));
}
.markdown-body :deep(table) {
    border-collapse: collapse;
    margin: 0.4rem 0;
}
.markdown-body :deep(th),
.markdown-body :deep(td) {
    border: 1px solid rgb(var(--color-border-main));
    padding: 0.25rem 0.5rem;
}
.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3) {
    font-weight: 700;
    margin: 0.5rem 0 0.25rem 0;
}
.markdown-body :deep(h1) { font-size: 1.25em; }
.markdown-body :deep(h2) { font-size: 1.15em; }
.markdown-body :deep(h3) { font-size: 1.05em; }
.markdown-body :deep(hr) {
    border: none;
    border-top: 1px solid rgb(var(--color-border-main));
    margin: 0.8rem 0;
}
</style>
