<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    accounts: { type: Array, required: true },
    modules: { type: Array, required: true },
});

const showCreate = ref(false);
const editingId = ref(null);

const createForm = useForm({ name: '', email: '', password: '', modules: [] });
const editForm = useForm({ name: '', email: '', password: '', modules: [] });

const submitCreate = () => {
    createForm.post('/platform/users', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
};

const startEdit = (account) => {
    editingId.value = account.id;
    editForm.clearErrors();
    editForm.name = account.name;
    editForm.email = account.email;
    editForm.password = '';
    editForm.modules = [...account.modules];
};

const submitEdit = (account) => {
    editForm.put(`/platform/users/${account.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
};

const destroy = (account) => {
    if (!confirm(`Hapus akun ${account.email}?`)) return;
    router.delete(`/platform/users/${account.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Akun Platform" />

    <PlatformLayout>
        <template #header>Akun Platform</template>

        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground leading-relaxed max-w-2xl">
                Akun pemilik memegang akses penuh tanpa perlu dicentang. Akun lain hanya bisa membuka modul yang dicentangkan di sini.
            </p>
            <button
                type="button"
                class="shrink-0 rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700 transition-colors"
                @click="showCreate = !showCreate"
            >
                {{ showCreate ? 'Batal' : 'Tambah Akun' }}
            </button>
        </div>

        <!-- Form tambah -->
        <form
            v-if="showCreate"
            class="mb-6 rounded-xl border border-border bg-card p-5"
            novalidate
            @submit.prevent="submitCreate"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1.5">Nama</label>
                    <input v-model="createForm.name" type="text" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    <p v-if="createForm.errors.name" class="text-xs text-destructive mt-1">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1.5">Email</label>
                    <input v-model="createForm.email" type="email" autocomplete="off" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    <p v-if="createForm.errors.email" class="text-xs text-destructive mt-1">{{ createForm.errors.email }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1.5">Kata Sandi</label>
                    <input v-model="createForm.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    <p v-if="createForm.errors.password" class="text-xs text-destructive mt-1">{{ createForm.errors.password }}</p>
                </div>
            </div>

            <fieldset class="mt-4">
                <legend class="text-sm font-medium text-foreground mb-2">Modul</legend>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="mod in modules"
                        :key="mod.name"
                        class="flex items-start gap-2 text-sm"
                        :class="mod.available ? 'text-foreground' : 'text-muted-foreground'"
                    >
                        <input
                            v-model="createForm.modules"
                            type="checkbox"
                            :value="mod.name"
                            :disabled="!mod.available"
                            class="mt-0.5 rounded border-border disabled:opacity-40"
                        />
                        <span>
                            {{ mod.label }}
                            <span v-if="mod.sensitive && mod.available" class="text-xs text-amber-600">· sensitif</span>
                            <span v-if="!mod.available" class="block text-xs">Halamannya belum ada</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <button
                type="submit"
                :disabled="createForm.processing"
                class="mt-5 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60 transition-colors"
            >
                Simpan Akun
            </button>
        </form>

        <!-- Daftar akun -->
        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Akun</th>
                            <th class="px-4 py-3">Modul</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <template v-for="account in accounts" :key="account.id">
                            <tr class="hover:bg-accent/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">
                                        {{ account.name }}
                                        <span v-if="account.is_owner" class="ml-1.5 rounded bg-slate-800 px-1.5 py-0.5 text-[0.65rem] font-semibold text-white uppercase tracking-wide">Pemilik</span>
                                        <span v-if="account.is_self" class="ml-1 text-xs text-muted-foreground">(Anda)</span>
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ account.email }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <span v-if="account.is_owner">Seluruh modul (akses penuh)</span>
                                    <span v-else-if="account.modules.length === 0">—</span>
                                    <span v-else>{{ account.modules.join(', ') }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="button" class="text-xs text-foreground hover:underline" @click="startEdit(account)">Ubah</button>
                                    <button
                                        v-if="!account.is_self"
                                        type="button"
                                        class="ml-3 text-xs text-destructive hover:underline"
                                        @click="destroy(account)"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>

                            <!-- Baris ubah -->
                            <tr v-if="editingId === account.id" class="bg-accent/20">
                                <td colspan="3" class="px-4 py-4">
                                    <form novalidate @submit.prevent="submitEdit(account)">
                                        <div class="grid gap-4 sm:grid-cols-3">
                                            <div>
                                                <label class="block text-xs font-medium text-foreground mb-1">Nama</label>
                                                <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                                                <p v-if="editForm.errors.name" class="text-xs text-destructive mt-1">{{ editForm.errors.name }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-foreground mb-1">Email</label>
                                                <input v-model="editForm.email" type="email" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                                                <p v-if="editForm.errors.email" class="text-xs text-destructive mt-1">{{ editForm.errors.email }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-foreground mb-1">Kata Sandi Baru <span class="text-muted-foreground">(opsional)</span></label>
                                                <input v-model="editForm.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                                                <p v-if="editForm.errors.password" class="text-xs text-destructive mt-1">{{ editForm.errors.password }}</p>
                                            </div>
                                        </div>

                                        <fieldset v-if="!account.is_owner" class="mt-4">
                                            <legend class="text-xs font-medium text-foreground mb-2">Modul</legend>
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                <label
                                                    v-for="mod in modules"
                                                    :key="mod.name"
                                                    class="flex items-start gap-2 text-sm"
                                                    :class="mod.available ? 'text-foreground' : 'text-muted-foreground'"
                                                >
                                                    <input
                                                        v-model="editForm.modules"
                                                        type="checkbox"
                                                        :value="mod.name"
                                                        :disabled="!mod.available"
                                                        class="mt-0.5 rounded border-border disabled:opacity-40"
                                                    />
                                                    <span>
                                                        {{ mod.label }}
                                                        <span v-if="mod.sensitive && mod.available" class="text-xs text-amber-600">· sensitif</span>
                                                        <span v-if="!mod.available" class="block text-xs">Halamannya belum ada</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </fieldset>
                                        <p v-else class="mt-4 text-xs text-muted-foreground">
                                            Akun pemilik selalu punya akses penuh — modulnya tidak diatur di sini.
                                        </p>

                                        <div class="mt-4 flex gap-2">
                                            <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60 transition-colors">
                                                Simpan
                                            </button>
                                            <button type="button" class="rounded-lg border border-border px-3.5 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors" @click="editingId = null">
                                                Batal
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </PlatformLayout>
</template>
