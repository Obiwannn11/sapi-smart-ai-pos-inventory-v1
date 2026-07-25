<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    email: { type: String, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

const resendForm = useForm({});
const logoutForm = useForm({});

const resend = () => resendForm.post('/verifikasi-email/kirim-ulang', { preserveScroll: true });
const logout = () => logoutForm.post('/logout');
</script>

<template>
    <Head title="Verifikasi Email" />

    <div class="min-h-screen bg-background px-6 py-14">
        <div class="mx-auto w-full max-w-md">

            <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold text-foreground tracking-tight leading-none">SAPI</span>
                <span class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-widest">POS</span>
            </div>

            <h1 class="mt-8 text-2xl font-bold text-foreground tracking-tight">Cek Email Anda</h1>
            <p class="mt-2 text-sm text-muted-foreground leading-relaxed">
                Kami mengirim tautan verifikasi ke
                <span class="font-medium text-foreground">{{ email }}</span>.
                Buka tautan itu untuk mulai memakai SAPI POS.
            </p>

            <div v-if="flash" role="status" class="mt-6 rounded-lg border border-border bg-card px-3.5 py-3 text-sm text-foreground">
                {{ flash }}
            </div>

            <div class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">Tidak menerima emailnya?</p>
                <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Periksa folder spam terlebih dahulu. Bila memang belum sampai, kami bisa mengirim ulang.
                </p>
                <button
                    :disabled="resendForm.processing"
                    class="mt-3 px-4 py-2 bg-primary text-primary-foreground text-sm font-semibold rounded-lg
                           hover:bg-primary/90 transition-colors duration-150 disabled:opacity-50"
                    @click="resend"
                >
                    {{ resendForm.processing ? 'Mengirim...' : 'Kirim Ulang Tautan' }}
                </button>
            </div>

            <p class="mt-6 text-xs text-muted-foreground leading-relaxed">
                Salah menuliskan alamat email? Keluar, lalu daftar ulang dengan alamat yang benar.
            </p>

            <button class="mt-4 text-sm font-medium text-primary hover:text-primary/80 transition-colors" @click="logout">
                Keluar
            </button>
        </div>
    </div>
</template>
