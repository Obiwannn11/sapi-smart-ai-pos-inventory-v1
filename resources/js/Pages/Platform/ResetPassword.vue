<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/platform/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Atur Ulang Kata Sandi — Platform" />

    <div class="min-h-screen flex items-center justify-center bg-background px-6 py-14">
        <div class="w-full max-w-sm">
            <div class="flex items-center gap-2.5 mb-8">
                <div class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                    <span class="text-white text-xs font-bold tracking-tight select-none">SP</span>
                </div>
                <div class="leading-none">
                    <span class="block text-lg font-bold text-foreground tracking-tight">SAPI</span>
                    <span class="block text-[0.625rem] font-semibold text-muted-foreground uppercase tracking-widest mt-0.5">
                        Platform Console
                    </span>
                </div>
            </div>

            <div class="mb-7">
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Atur Ulang Kata Sandi</h1>
                <p class="text-sm text-muted-foreground mt-1.5 leading-relaxed">
                    Pilih kata sandi baru, minimal 8 karakter.
                </p>
            </div>

            <form novalidate @submit.prevent="submit">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-foreground mb-1.5">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground
                               focus:outline-none focus:ring-2 focus:ring-slate-800/20 focus:border-slate-800"
                    />
                    <p v-if="form.errors.email" class="text-xs text-destructive mt-1.5">{{ form.errors.email }}</p>
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-foreground mb-1.5">Kata Sandi Baru</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground
                               focus:outline-none focus:ring-2 focus:ring-slate-800/20 focus:border-slate-800"
                    />
                    <p v-if="form.errors.password" class="text-xs text-destructive mt-1.5">{{ form.errors.password }}</p>
                </div>

                <div class="mb-5">
                    <label for="password_confirmation" class="block text-sm font-medium text-foreground mb-1.5">Ulangi Kata Sandi</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground
                               focus:outline-none focus:ring-2 focus:ring-slate-800/20 focus:border-slate-800"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white
                           hover:bg-slate-700 disabled:opacity-60 transition-colors"
                >
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Kata Sandi' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-muted-foreground">
                <Link href="/platform/login" class="hover:text-foreground transition-colors">Kembali ke halaman masuk</Link>
            </p>
        </div>
    </div>
</template>
