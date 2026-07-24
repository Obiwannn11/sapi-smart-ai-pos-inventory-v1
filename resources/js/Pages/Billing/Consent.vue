<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    type: { type: String, required: true },
    document: { type: Object, required: true },
    agreement: { type: Object, default: null },
    can_agree: { type: Boolean, required: true },
    blocked_reason: { type: String, default: null },
});

const isSubsidized = computed(() => props.type === 'subsidized');

const scroller = ref(null);
const readToEnd = ref(false);

const form = useForm({
    version: props.document.version,
    agreed: false,
});

/**
 * Tombol setuju baru hidup setelah teksnya benar-benar digulir sampai bawah
 * DAN kotak centangnya dicentang sendiri. Keduanya disengaja: persetujuan yang
 * bisa diklik tanpa melewati teksnya bukan persetujuan, hanya formalitas.
 */
const checkScrolled = () => {
    const el = scroller.value;
    if (!el) return;
    // Toleransi 24px — beberapa peramban menyisakan pecahan piksel di dasar,
    // dan tombol yang tidak pernah hidup jauh lebih menyakitkan daripada
    // toleransi kecil ini.
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 24) {
        readToEnd.value = true;
    }
};

// Layar tinggi bisa memuat seluruh teks tanpa perlu digulir sama sekali.
// Tanpa pemeriksaan ini tombolnya tak akan pernah bisa ditekan di sana.
onMounted(() => {
    const el = scroller.value;
    if (el && el.scrollHeight <= el.clientHeight + 24) {
        readToEnd.value = true;
    }
});

const canSubmit = computed(() => props.can_agree && readToEnd.value && form.agreed && !form.processing);

const submit = () => {
    if (!canSubmit.value) return;
    form.post(`/langganan/persetujuan/${props.type}`, { preserveScroll: true });
};

const alreadyCurrent = computed(() => props.agreement?.is_current === true);
</script>

<template>
    <Head title="Persetujuan Langganan" />

    <div class="min-h-screen bg-background px-6 py-14">
        <div class="mx-auto w-full max-w-2xl">

            <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold text-foreground tracking-tight leading-none">SAPI</span>
                <span class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-widest">POS</span>
            </div>

            <h1 class="mt-8 text-2xl font-bold text-foreground tracking-tight">Persetujuan Langganan</h1>
            <p class="mt-1.5 text-sm text-muted-foreground">
                {{ isSubsidized ? 'Jalur subsidi UMKM' : 'Jalur harga normal' }} · versi {{ document.version }}
            </p>

            <div
                v-if="alreadyCurrent"
                class="mt-6 rounded-lg border border-border bg-card px-3.5 py-3 text-sm text-foreground"
            >
                Anda sudah menyetujui versi ini pada {{ agreement.agreed_at }}.
            </div>
            <div
                v-else-if="agreement"
                class="mt-6 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3.5 py-3 text-sm text-foreground"
            >
                Anda menyetujui versi {{ agreement.version }} pada {{ agreement.agreed_at }}.
                Teksnya sudah diperbarui sejak itu — silakan baca dan setujui versi {{ document.version }}.
            </div>

            <div
                ref="scroller"
                @scroll="checkScrolled"
                class="mt-6 max-h-[26rem] overflow-y-auto rounded-xl border border-border bg-card px-6 py-5"
            >
                <article class="consent-body text-sm text-foreground leading-relaxed" v-html="document.html" />
            </div>

            <p v-if="!readToEnd" class="mt-2 text-xs text-muted-foreground">
                Gulir sampai akhir teks untuk melanjutkan.
            </p>

            <form v-if="can_agree" class="mt-6" @submit.prevent="submit">
                <label class="flex items-start gap-3 text-sm text-foreground">
                    <input
                        v-model="form.agreed"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring"
                    />
                    <span>
                        Saya sudah membaca dan menyetujui ketentuan di atas, dan saya adalah pemilik usaha ini.
                    </span>
                </label>

                <p v-if="form.errors.agreed" role="alert" class="mt-2 text-xs text-destructive">
                    {{ form.errors.agreed }}
                </p>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="mt-5 px-4 py-2.5 bg-primary text-primary-foreground text-sm font-semibold rounded-lg
                           hover:bg-primary/90 active:bg-primary/80 transition-colors duration-150
                           focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2
                           disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Saya Setuju' }}
                </button>
            </form>

            <p v-else-if="blocked_reason" class="mt-6 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3.5 py-3 text-sm text-foreground">
                {{ blocked_reason }}
            </p>

            <p v-else class="mt-6 rounded-lg border border-border bg-card px-3.5 py-3 text-sm text-muted-foreground">
                Persetujuan ini hanya bisa diberikan oleh pemilik usaha.
            </p>

            <p class="mt-8 text-sm">
                <Link href="/langganan" class="font-medium text-primary hover:text-primary/80 transition-colors duration-150">
                    Kembali ke halaman langganan
                </Link>
            </p>
        </div>
    </div>
</template>

<style scoped>
/*
 * CSS biasa, bukan @apply: Tailwind v4 menuntut direktif @reference di tiap
 * blok <style> scoped, dan tak ada satu pun komponen lain di proyek ini yang
 * memakai pola itu. Warnanya diwarisi dari `text-foreground` di elemen
 * pembungkusnya, jadi yang perlu diatur di sini hanya ukuran dan jarak.
 */
.consent-body :deep(h1) { font-size: 1.125rem; font-weight: 700; margin: 0 0 0.25rem; }
.consent-body :deep(h2) { font-size: 0.875rem; font-weight: 600; margin: 1.5rem 0 0.5rem; }
.consent-body :deep(p) { margin-bottom: 0.75rem; }
.consent-body :deep(ul) { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.75rem; }
.consent-body :deep(li) { margin-bottom: 0.25rem; }
.consent-body :deep(strong) { font-weight: 600; }
</style>
