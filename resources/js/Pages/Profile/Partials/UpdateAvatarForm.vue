<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const fileInput = ref(null);
const previewUrl = ref(null);

const form = useForm({
    avatar: null,
});

const onPick = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.avatar = file;
    previewUrl.value = URL.createObjectURL(file);
};

const submit = () => {
    form.post(route('profile.avatar.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            previewUrl.value = null;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const removeAvatar = () => {
    if (!confirm('Remover sua foto de perfil? Voltará a exibir suas iniciais.')) return;
    router.delete(route('profile.avatar.destroy'), { preserveScroll: true });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Foto de perfil</h2>
            <p class="mt-1 text-sm text-gray-600">
                Sua foto aparece nos cards, comentários e relatórios. Sem foto, exibimos suas iniciais.
                Formatos: JPG, PNG, GIF ou WebP — até 5MB.
            </p>
        </header>

        <div class="mt-6 flex items-center gap-6">
            <!-- Preview: arquivo recém-escolhido > foto atual > iniciais -->
            <img v-if="previewUrl" :src="previewUrl"
                class="h-20 w-20 rounded-full object-cover ring-2 ring-gray-200" alt="preview" />
            <Avatar v-else :user="user" :size="80" />

            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                        Escolher foto
                        <input ref="fileInput" type="file" class="hidden"
                            accept="image/png,image/jpeg,image/gif,image/webp"
                            @change="onPick" />
                    </label>
                    <button v-if="form.avatar" type="button" @click="submit" :disabled="form.processing"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition disabled:opacity-50">
                        {{ form.processing ? 'Enviando…' : 'Salvar foto' }}
                    </button>
                    <button v-if="user?.avatar_url && !form.avatar" type="button" @click="removeAvatar"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Remover foto
                    </button>
                </div>
                <p v-if="form.errors.avatar" class="text-sm text-red-600">{{ form.errors.avatar }}</p>
            </div>
        </div>
    </section>
</template>
