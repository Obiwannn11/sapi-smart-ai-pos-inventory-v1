<script setup>
/**
 * Pendaftaran faktor kedua akun platform ([BL-013]).
 *
 * Tiga hal yang halaman ini harus katakan terus terang, karena masing-masing
 * adalah cara pengguna terkunci dari akunnya sendiri:
 *
 *   1. Kode pemulihan hanya ditampilkan SEKALI. Kalau tidak dikatakan, orang
 *      akan menutup tab dan mengira bisa melihatnya lagi nanti.
 *   2. Pendaftarannya belum selesai sampai satu kode diketikkan. Rahasia yang
 *      dibangkitkan lalu tabnya ditutup tidak mengunci apa pun — dan itu
 *      disengaja.
 *   3. Kode yang ditolak paling sering karena jam perangkat meleset, bukan
 *      karena salah ketik.
 */
import { computed } from 'vue';
import { useForm, usePage, Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import Button from '@/Components/Button.vue';
import { BUSINESS_TZ } from '@/support/date';

const props = defineProps({
    enabled: { type: Boolean, default: false },
    recoveryCodesRemaining: { type: Number, default: 0 },
    confirmedAt: { type: String, default: null },
});

const page = usePage();

// Keduanya datang lewat flash sekali-tampil, bukan prop tetap: rahasia dan
// kode pemulihan tidak boleh ikut di setiap kunjungan berikutnya ke halaman ini.
const setup = computed(() => page.props.flash?.twoFactorSetup ?? null);
const recoveryCodes = computed(() => page.props.flash?.recoveryCodes ?? null);

const startForm = useForm({});
const confirmForm = useForm({ code: '' });
const passwordForm = useForm({ password: '' });

const start = () => startForm.post('/platform/keamanan/two-factor', { preserveScroll: true });

const confirm = () => confirmForm.post('/platform/keamanan/two-factor/konfirmasi', {
    preserveScroll: true,
    onSuccess: () => confirmForm.reset('code'),
});

const regenerate = () => passwordForm.post('/platform/keamanan/two-factor/kode-pemulihan', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset('password'),
});

const disable = () => passwordForm.delete('/platform/keamanan/two-factor', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset('password'),
});

const copyCodes = () => {
    if (recoveryCodes.value) {
        navigator.clipboard?.writeText(recoveryCodes.value.join('\n'));
    }
};
</script>

<template>
    <Head title="Keamanan Akun" />

    <PlatformLayout>
        <PageHeader
            title="Keamanan Akun"
            description="Faktor kedua untuk akun platform Anda sendiri. Satu akun di sini memegang data administratif seluruh klien."
        />

        <div class="max-w-2xl space-y-5">
            <!-- Keadaan sekarang -->
            <div class="rounded-xl border border-border bg-card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Verifikasi Dua Langkah</h2>
                        <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                            <template v-if="enabled">
                                Aktif sejak {{ new Date(confirmedAt).toLocaleDateString('id-ID', { timeZone: BUSINESS_TZ, day: 'numeric', month: 'long', year: 'numeric' }) }}.
                                Sisa {{ recoveryCodesRemaining }} kode pemulihan.
                            </template>
                            <template v-else>
                                Belum aktif. Siapa pun yang memegang kata sandi Anda bisa langsung masuk.
                            </template>
                        </p>
                    </div>
                    <span
                        :class="[
                            'shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                            enabled ? 'bg-success/10 text-success' : 'bg-amber-100 text-amber-800',
                        ]"
                    >
                        {{ enabled ? 'Aktif' : 'Belum aktif' }}
                    </span>
                </div>

                <p class="mt-4 text-xs text-muted-foreground leading-relaxed">
                    Dipakai aplikasi authenticator (Google Authenticator, Authy, 1Password), bukan OTP lewat surel —
                    surel adalah jalur pemulihan kata sandi akun ini, jadi kalau kotak masuknya jebol, dua-duanya jebol sekaligus.
                </p>
            </div>

            <!-- Kode pemulihan, sekali tampil -->
            <div v-if="recoveryCodes" class="rounded-xl border border-amber-300 bg-amber-50 p-5">
                <h2 class="text-sm font-semibold text-amber-900">Simpan kode pemulihan ini sekarang</h2>
                <p class="mt-1 text-xs text-amber-800 leading-relaxed">
                    Ditampilkan <strong>sekali saja</strong>. Tanpa kode ini, kehilangan ponsel berarti akun terkunci permanen.
                    Tiap kode hanya bisa dipakai satu kali.
                </p>

                <ul class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-sm text-amber-900">
                    <li v-for="code in recoveryCodes" :key="code" class="rounded bg-white/70 px-2 py-1">{{ code }}</li>
                </ul>

                <button
                    type="button"
                    class="mt-3 text-xs font-medium text-amber-900 underline hover:no-underline"
                    @click="copyCodes"
                >
                    Salin semua
                </button>
            </div>

            <!-- Pendaftaran -->
            <div v-if="!enabled" class="rounded-xl border border-border bg-card p-5">
                <h2 class="text-sm font-semibold text-foreground">Daftarkan authenticator</h2>

                <template v-if="!setup">
                    <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                        Kunci akan ditampilkan untuk dimasukkan ke aplikasi authenticator Anda.
                        Pendaftaran belum berlaku sampai Anda mengetikkan satu kode dari aplikasi itu.
                    </p>
                    <Button size="sm" class="mt-4" :disabled="startForm.processing" @click="start">
                        {{ startForm.processing ? 'Menyiapkan…' : 'Mulai' }}
                    </Button>
                </template>

                <template v-else>
                    <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                        Tambahkan akun baru di aplikasi authenticator Anda secara manual, lalu masukkan kunci berikut:
                    </p>

                    <div class="mt-3 rounded-lg border border-border bg-muted/40 px-3 py-2.5">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">Kunci</p>
                        <p class="mt-0.5 font-mono text-sm tracking-wide text-foreground break-all">{{ setup.secret }}</p>
                    </div>

                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                            Tampilkan tautan otpauth:// (untuk aplikasi yang menerima tautan)
                        </summary>
                        <p class="mt-1.5 break-all rounded bg-muted/40 px-2 py-1.5 font-mono text-[0.7rem] text-muted-foreground">
                            {{ setup.uri }}
                        </p>
                    </details>

                    <form class="mt-4" @submit.prevent="confirm">
                        <label for="confirm-code" class="block text-sm font-medium text-foreground mb-1.5">
                            Kode dari aplikasi
                        </label>
                        <input
                            id="confirm-code"
                            v-model="confirmForm.code"
                            type="text"
                            inputmode="numeric"
                            placeholder="000000"
                            autocomplete="one-time-code"
                            class="w-40 rounded-lg border border-border bg-card px-3 py-2 font-mono text-lg tracking-[0.3em] text-foreground focus:outline-none focus:ring-2 focus:ring-slate-800/30"
                        />
                        <p v-if="confirmForm.errors.code" role="alert" class="mt-1.5 text-xs text-destructive">
                            {{ confirmForm.errors.code }}
                        </p>

                        <div class="mt-4">
                            <Button size="sm" type="submit" :disabled="confirmForm.processing">
                                {{ confirmForm.processing ? 'Memeriksa…' : 'Aktifkan' }}
                            </Button>
                        </div>
                    </form>
                </template>
            </div>

            <!-- Pengelolaan setelah aktif -->
            <div v-if="enabled" class="rounded-xl border border-border bg-card p-5">
                <h2 class="text-sm font-semibold text-foreground">Kelola</h2>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                    Kata sandi diminta untuk keduanya — sesi yang tertinggal terbuka di komputer bersama tidak boleh bisa
                    melepas lapisan ini dengan satu klik.
                </p>

                <div class="mt-4 max-w-xs">
                    <label for="confirm-password" class="block text-sm font-medium text-foreground mb-1.5">Kata sandi</label>
                    <input
                        id="confirm-password"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-slate-800/30"
                    />
                    <p v-if="passwordForm.errors.password" role="alert" class="mt-1.5 text-xs text-destructive">
                        {{ passwordForm.errors.password }}
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" :disabled="passwordForm.processing" @click="regenerate">
                        Terbitkan ulang kode pemulihan
                    </Button>
                    <Button size="sm" variant="destructive" :disabled="passwordForm.processing" @click="disable">
                        Matikan faktor kedua
                    </Button>
                </div>
            </div>
        </div>
    </PlatformLayout>
</template>
