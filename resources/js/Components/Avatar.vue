<script setup>
import { computed } from 'vue';

/**
 * Avatar circular do usuário. Mostra a foto de perfil quando o user tem
 * avatar_url; senão cai pras iniciais (primeira letra do nome + primeira
 * letra do ÚLTIMO sobrenome — "Felipe Santana" → "FS", desambigua os
 * três Felipes do time).
 */
const props = defineProps({
    user: { type: Object, default: null }, // espera { name, avatar_url? }
    size: { type: Number, default: 32 },   // px
});

const initials = computed(() => {
    const name = (props.user?.name || '').trim();
    if (!name) return '?';
    const words = name.split(/\s+/).filter(Boolean);
    if (words.length === 1) return words[0].charAt(0).toUpperCase();
    return (words[0].charAt(0) + words[words.length - 1].charAt(0)).toUpperCase();
});

const sizeStyle = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    fontSize: `${Math.max(9, Math.round(props.size * 0.36))}px`,
}));
</script>

<template>
    <img v-if="user?.avatar_url"
        :src="user.avatar_url"
        :alt="user?.name || 'avatar'"
        :style="sizeStyle"
        class="rounded-full object-cover flex-shrink-0" />
    <div v-else
        :style="sizeStyle"
        class="rounded-full bg-brand text-white flex items-center justify-center font-bold flex-shrink-0 select-none leading-none">
        {{ initials }}
    </div>
</template>
