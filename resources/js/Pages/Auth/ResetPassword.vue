<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

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

const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});

const fieldClass = (hasError) => [
    'w-full px-3 py-2.5 bg-card text-sm text-foreground',
    'placeholder:text-muted-foreground border rounded-lg',
    'transition-colors duration-150',
    'focus:outline-none focus:ring-2 focus:ring-offset-1',
    hasError
        ? 'border-destructive focus:ring-destructive/50'
        : 'border-border hover:border-muted-foreground/35 focus:ring-ring',
];
</script>

<template>
    <Head title="Atur Ulang Kata Sandi" />

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- Mobile brand strip -->
        <div class="md:hidden flex-shrink-0 h-12 bg-primary flex items-center px-6 gap-2" aria-hidden="true">
            <span class="text-primary-foreground font-bold text-lg tracking-tight leading-none">SAPI</span>
            <span class="text-primary-foreground/40 text-[0.65rem] font-semibold uppercase tracking-widest mt-px">POS</span>
        </div>

        <!-- Brand panel — desktop only -->
        <div
            class="hidden md:flex md:w-[400px] lg:w-[460px] flex-shrink-0 bg-primary relative overflow-hidden flex-col justify-between p-10 lg:p-12"
            aria-hidden="true"
        >
            <span
                class="absolute -bottom-24 -right-10 text-[20rem] font-black leading-none tracking-tighter
                       text-primary-foreground/[0.055] select-none pointer-events-none"
            >S</span>

            <div class="relative z-10">
                <div class="flex items-baseline gap-2">
                    <span class="text-[1.625rem] font-bold text-primary-foreground tracking-tight leading-none">SAPI</span>
                    <span class="text-[0.65rem] font-semibold text-primary-foreground/40 uppercase tracking-widest">POS</span>
                </div>
                <p class="mt-4 text-[0.9375rem] text-primary-foreground/70 leading-relaxed max-w-[200px]">
                    Kelola kasir, stok, dan laporan dari satu tempat.
                </p>
            </div>

            <p class="relative z-10 text-xs text-primary-foreground/28 leading-relaxed">
                Untuk warung dan restoran Indonesia
            </p>
        </div>

        <!-- Form panel -->
        <main class="flex-1 flex items-center justify-center bg-background px-6 py-14 md:py-0">
            <div class="w-full max-w-sm">

                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-foreground tracking-tight">Atur Ulang Kata Sandi</h1>
                    <p class="text-sm text-muted-foreground mt-1.5 leading-relaxed">
                        Pilih kata sandi baru, minimal 8 karakter.
                    </p>
                </div>

                <form novalidate @submit.prevent="submit">
                    <div>
                        <label for="reset-email" class="block text-sm font-medium text-foreground mb-1.5">Email</label>
                        <input
                            id="reset-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            :class="fieldClass(!!form.errors.email)"
                        />
                        <p v-if="form.errors.email" role="alert" class="mt-1.5 text-xs text-destructive">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="mt-5">
                        <label for="reset-password" class="block text-sm font-medium text-foreground mb-1.5">Kata Sandi Baru</label>
                        <input
                            id="reset-password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :class="fieldClass(!!form.errors.password)"
                        />
                        <p v-if="form.errors.password" role="alert" class="mt-1.5 text-xs text-destructive">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="mt-5">
                        <label for="reset-password-confirm" class="block text-sm font-medium text-foreground mb-1.5">Ulangi Kata Sandi</label>
                        <input
                            id="reset-password-confirm"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :class="fieldClass(false)"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-7 px-4 py-2.5 bg-primary text-primary-foreground
                               text-sm font-semibold rounded-lg
                               hover:bg-primary/90 active:bg-primary/80
                               transition-colors duration-150
                               focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2
                               disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Kata Sandi' }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-muted-foreground">
                    <Link href="/login" class="font-medium text-primary hover:text-primary/80 transition-colors duration-150">
                        Kembali ke halaman masuk
                    </Link>
                </p>
            </div>
        </main>
    </div>
</template>
