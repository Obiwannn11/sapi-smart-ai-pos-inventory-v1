<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import DataTable from '@/Components/Platform/DataTable.vue';
import StatusBadge from '@/Components/Platform/StatusBadge.vue';
import FormField from '@/Components/Platform/FormField.vue';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { inputClass } from '@/support/platform';

defineProps({
    accounts: { type: Array, required: true },
    modules: { type: Array, required: true },
});

const columns = [
    { key: 'account', label: 'Akun' },
    { key: 'modules', label: 'Modul' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

const showForm = ref(false);
const editing = ref(null);

const form = useForm({ name: '', email: '', password: '', modules: [] });

const openCreate = () => {
    form.reset();
    form.clearErrors();
    editing.value = null;
    showForm.value = true;
};

const openEdit = (account) => {
    form.name = account.name;
    form.email = account.email;
    form.password = '';
    form.modules = [...account.modules];
    form.clearErrors();
    editing.value = account;
    showForm.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            editing.value = null;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`/platform/users/${editing.value.id}`, options);
    } else {
        form.post('/platform/users', options);
    }
};

const deleteTarget = ref(null);

const confirmDelete = () => {
    router.delete(`/platform/users/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => { deleteTarget.value = null; },
    });
};
</script>

<template>
    <Head title="Akun Platform" />

    <PlatformLayout>
        <PageHeader
            title="Akun Platform"
            description="Akun pemilik memegang akses penuh tanpa perlu dicentang. Akun lain hanya bisa membuka modul yang dicentangkan di sini."
        >
            <Button size="sm" @click="openCreate">Tambah Akun</Button>
        </PageHeader>

        <DataTable v-slot="{ cellClass }" :columns="columns" :count="accounts.length" empty="Belum ada akun platform.">
            <tr v-for="account in accounts" :key="account.id" class="hover:bg-accent/30 transition-colors">
                <td :class="cellClass">
                    <p class="font-medium text-foreground">
                        {{ account.name }}
                        <StatusBadge v-if="account.is_owner" class="ml-1.5" label="Pemilik" tone="info" />
                        <span v-if="account.is_self" class="ml-1 text-xs text-muted-foreground">(Anda)</span>
                    </p>
                    <p class="text-xs text-muted-foreground">{{ account.email }}</p>
                </td>

                <td :class="[cellClass, 'text-muted-foreground']">
                    <span v-if="account.is_owner">Seluruh modul (akses penuh)</span>
                    <span v-else-if="account.modules.length === 0">—</span>
                    <span v-else>{{ account.modules.join(', ') }}</span>
                </td>

                <td :class="[cellClass, 'text-right whitespace-nowrap']">
                    <div class="flex items-center justify-end gap-2">
                        <Button size="sm" variant="soft" @click="openEdit(account)">Ubah</Button>
                        <Button
                            v-if="!account.is_self"
                            size="sm"
                            variant="destructiveSoft"
                            @click="deleteTarget = account"
                        >
                            Hapus
                        </Button>
                    </div>
                </td>
            </tr>
        </DataTable>

        <Modal
            :show="showForm"
            :title="editing ? 'Ubah akun platform' : 'Tambah akun platform'"
            max-width="max-w-lg"
            @close="showForm = false"
        >
            <form class="space-y-4" novalidate @submit.prevent="submit">
                <FormField label="Nama" :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="inputClass" />
                </FormField>

                <FormField label="Email" :error="form.errors.email">
                    <input v-model="form.email" type="email" autocomplete="off" :class="inputClass" />
                </FormField>

                <FormField
                    :label="editing ? 'Kata sandi baru' : 'Kata sandi'"
                    :hint="editing ? 'Kosongkan bila tidak diubah.' : ''"
                    :error="form.errors.password"
                >
                    <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass" />
                </FormField>

                <fieldset v-if="!editing?.is_owner">
                    <legend class="text-sm font-medium text-foreground mb-2">Modul</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="mod in modules"
                            :key="mod.name"
                            class="flex items-start gap-2 text-sm"
                            :class="mod.available ? 'text-foreground' : 'text-muted-foreground'"
                        >
                            <input
                                v-model="form.modules"
                                type="checkbox"
                                :value="mod.name"
                                :disabled="!mod.available"
                                class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring disabled:opacity-40"
                            />
                            <span>
                                {{ mod.label }}
                                <span v-if="mod.sensitive && mod.available" class="text-xs text-amber-700">· sensitif</span>
                                <span v-if="!mod.available" class="block text-xs">Halamannya belum ada</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors.modules" class="mt-1 text-xs text-destructive">{{ form.errors.modules }}</p>
                </fieldset>

                <p v-else class="text-xs text-muted-foreground leading-relaxed">
                    Akun pemilik selalu punya akses penuh — modulnya tidak diatur di sini.
                </p>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showForm = false">Batal</Button>
                    <Button :loading="form.processing" @click="submit">Simpan</Button>
                </div>
            </template>
        </Modal>

        <ConfirmDialog
            :show="deleteTarget !== null"
            title="Hapus akun platform"
            :message="`Akun ${deleteTarget?.email} akan dihapus dan tidak bisa masuk lagi. Jejak auditnya tetap tersimpan.`"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </PlatformLayout>
</template>
