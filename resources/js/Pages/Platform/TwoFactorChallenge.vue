<script setup>
/**
 * Langkah kedua login platform ([BL-013]).
 *
 * Halaman ini berdiri di antara "kata sandi benar" dan "masuk". Sesinya BELUM
 * terautentikasi saat halaman ini terbuka — yang tersimpan di sesi hanya id
 * yang menunggu. Karena itu ia tidak memakai layout panel: belum ada nav yang
 * boleh ditampilkan.
 */
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';

const form = useForm({ code: '' });

const usingRecoveryCode = ref(false);

const submit = () => {
    form.post('/platform/two-factor', {
        onFinish: () => form.reset('code'),
    });
};

const toggleMode = () => {
    usingRecoveryCode.value = !usingRecoveryCode.value;
    form.reset('code');
    form.clearErrors();
};
</script>

<template>
    <Head title="Verifikasi — Platform" />

    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Panel merek, disamakan dengan halaman masuk supaya langkah kedua
             tidak terasa seperti halaman dari aplikasi lain. -->
        <div class="md:hidden flex-shrink-0 h-12 bg-slate-800 flex items-center px-6 gap-2">
            <span class="text-white font-bold text-lg tracking-tight leading-none">SAPI</span>
            <span class="text-white/40 text-[0.65rem] font-semibold uppercase tracking-widest mt-px">PLATFORM</span>
        </div>

        <div class="hidden md:flex md:w-[400px] lg:w-[460px] flex-shrink-0 bg-slate-800 relative overflow-hidden flex-col justify-between p-10 lg:p-12">
            <span
                aria-hidden="true"
                class="absolute -bottom-24 -right-10 text-[20rem] font-black leading-none tracking-tighter text-white/[0.03] select-none"
            >S</span>

            <div class="relative z-10">
                <span class="flex items-center gap-2">
                    <span class="text-white font-bold text-2xl tracking-tight leading-none">SAPI</span>
                    <span class="text-white/40 text-[0.7rem] font-semibold uppercase tracking-widest mt-px">PLATFORM</span>
                </span>
                <p class="mt-4 text-[0.9375rem] text-white/70 leading-relaxed max-w-[220px]">
                    Satu langkah lagi. Buka aplikasi authenticator Anda.
                </p>
            </div>

            <p class="relative z-10 text-xs text-white/28 leading-relaxed">
                Kode berganti tiap 30 detik
            </p>
        </div>

        <!-- Form -->
        <main class="flex-1 flex items-center justify-center bg-background px-6 py-14 md:py-0">
            <div class="w-full max-w-sm">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-foreground tracking-tight">Verifikasi Dua Langkah</h1>
                    <p class="text-sm text-muted-foreground mt-1.5 leading-relaxed">
                        <template v-if="usingRecoveryCode">
                            Masukkan salah satu kode pemulihan yang Anda simpan saat mendaftarkan authenticator.
                            Setiap kode hanya bisa dipakai sekali.
                        </template>
                        <template v-else>
                            Masukkan kode enam angka dari aplikasi authenticator Anda.
                        </template>
                    </p>
                </div>

                <form @submit.prevent="submit" novalidate>
                    <label for="platform-2fa-code" class="block text-sm font-medium text-foreground mb-1.5">
                        {{ usingRecoveryCode ? 'Kode Pemulihan' : 'Kode Authenticator' }}
                    </label>
                    <input
                        id="platform-2fa-code"
                        v-model="form.code"
                        type="text"
                        :inputmode="usingRecoveryCode ? 'text' : 'numeric'"
                        :autocomplete="usingRecoveryCode ? 'off' : 'one-time-code'"
                        :placeholder="usingRecoveryCode ? 'XXXXX-XXXXX' : '000000'"
                        autofocus
                        :aria-invalid="!!form.errors.code"
                        aria-describedby="platform-2fa-error"
                        :class="[
                            'w-full px-3 py-2.5 bg-card text-foreground border rounded-lg',
                            'transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1',
                            usingRecoveryCode ? 'text-sm tracking-wide' : 'text-lg tracking-[0.4em] font-mono',
                            form.errors.code
                                ? 'border-destructive focus:ring-destructive/50'
                                : 'border-border hover:border-muted-foreground/35 focus:ring-slate-800/30',
                        ]"
                    />
                    <p v-if="form.errors.code" id="platform-2fa-error" role="alert" class="mt-1.5 text-xs text-destructive">
                        {{ form.errors.code }}
                    </p>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-6 w-full py-2.5 bg-slate-800 text-white text-sm font-semibold rounded-lg hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-800/40 focus:ring-offset-1 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Memeriksa…' : 'Verifikasi' }}
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between text-xs">
                    <button type="button" class="text-slate-700 hover:text-slate-900 font-medium" @click="toggleMode">
                        {{ usingRecoveryCode ? 'Pakai kode authenticator' : 'Kehilangan perangkat? Pakai kode pemulihan' }}
                    </button>
                    <Link href="/platform/login" class="text-muted-foreground hover:text-foreground">
                        Kembali
                    </Link>
                </div>
            </div>
        </main>
    </div>
</template>
