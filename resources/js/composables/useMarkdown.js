import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({ breaks: true, gfm: true });

// Hook único de sanitização, compartilhado por todos os renderizadores de
// markdown do sistema (descrição de cards, comentários, chat do Icarus).
//
// Regras:
// - Links externos abrem em nova aba com rel=noopener noreferrer.
// - <img> é permitido (precisamos pras imagens coladas), mas o src só pode
//   ser caminho relativo /storage/... ou http(s). Qualquer outro protocolo
//   (javascript:, data:, etc.) tem o src removido — defesa anti-XSS além do
//   que o DOMPurify já bloqueia por padrão.
let hookRegistered = false;

function registerHook() {
    if (hookRegistered) return;
    hookRegistered = true;

    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (node.tagName === 'A') {
            const href = node.getAttribute('href') || '';
            if (/^https?:/i.test(href)) {
                node.setAttribute('target', '_blank');
                node.setAttribute('rel', 'noopener noreferrer');
            }
        }

        if (node.tagName === 'IMG') {
            const src = node.getAttribute('src') || '';
            const isSafe = src.startsWith('/storage/')
                || src.startsWith('/')
                || /^https?:\/\//i.test(src);
            if (!isSafe) {
                node.removeAttribute('src');
            }
        }
    });
}

/**
 * Converte markdown em HTML sanitizado, pronto pra v-html.
 * @param {string} text
 * @returns {string}
 */
export function renderMarkdown(text) {
    if (!text) return '';
    registerHook();
    const raw = marked.parse(text);
    return DOMPurify.sanitize(raw, { ADD_ATTR: ['target', 'rel'] });
}

export function useMarkdown() {
    return { renderMarkdown };
}
