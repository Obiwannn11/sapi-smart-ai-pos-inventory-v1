<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    businessTypes: { type: Object, default: () => ({}) },
    /**
     * Kapabilitas yang bisa dinyalakan dari sini, satu baris per fitur:
     * `{ name, label, description }`. Datang dari server supaya menambah fitur
     * tidak berarti menyunting berkas ini.
     */
    featureCatalog: { type: Array, default: () => [] },
    /** Peta `jenis usaha → daftar nama fitur`. */
    featurePresets: { type: Object, default: () => ({}) },
    /** Centang awal, sebelum jenis usaha apa pun dipilih. */
    defaultFeatures: { type: Array, default: () => [] },
    /**
     * Masa gratis apa adanya dari server: lama, jarak terbit tagihan pertama,
     * dan paket tujuan sesudahnya (`null` bila memang tak ada perpindahan).
     * Tidak ada satu angka pun yang boleh ditulis ulang di berkas ini — lihat
     * `PublicPricing::trialNotice()`.
     */
    trial: {
        type: Object,
        default: () => ({ months: 0, invoice_lead_days: 0, post_trial_plan: null }),
    },
});

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

/**
 * Kalimat pemberitahuannya disusun di sini, bukan di template, supaya kedua
 * keadaannya terbaca berdampingan.
 *
 * Tanpa paket tujuan, halaman ini tetap TIDAK BOLEH diam: yang mendaftar hari
 * ini tetap menandatangani masa gratis yang berakhir, dan kalimat yang hanya
 * berbunyi "gratis" akan terbaca sebagai gratis selamanya.
 */
const trialNotice = computed(() => {
    const bulan = props.trial.months;
    const target = props.trial.post_trial_plan;

    if (!target) {
        return {
            headline: `Gratis ${bulan} bulan pertama`,
            body: 'Ini masa coba, bukan paket gratis selamanya. Tanggal berakhirnya '
                + 'tercantum di halaman Langganan begitu akun Anda jadi.',
        };
    }

    return {
        headline: `Gratis ${bulan} bulan pertama`,
        body: `Setelah itu akun Anda otomatis berlanjut ke paket ${target.name}, `
            + `${formatRupiah(target.price)}/bulan. Tagihan pertamanya terbit `
            + `${props.trial.invoice_lead_days} hari sebelum masa gratis berakhir, `
            + 'jadi Anda tahu angkanya sebelum jatuh tempo.',
    };
});

const form = useForm({
    business_name: '',
    business_type: '',
    // Yang dikirim adalah HASIL AKHIR centangnya, bukan nama presetnya. Preset
    // hanya mengisi daftar ini; apa pun yang tersisa saat tombol ditekan itulah
    // yang didapat tenant.
    features: [...props.defaultFeatures],
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

/**
 * Ganti jenis usaha, ganti isi centangnya — termasuk menimpa centang yang sudah
 * disentuh sendiri.
 *
 * Alternatifnya, "berhenti menerapkan preset begitu pengguna menyentuh daftar",
 * mengejutkan ke arah yang lebih buruk: orang yang salah pilih "Retail" lalu
 * membetulkannya jadi "Kuliner" akan mendapat daftar retail yang tidak pernah
 * ia minta, tanpa petunjuk apa pun bahwa pilihan barunya diabaikan. Menimpa itu
 * terlihat: daftarnya berubah di depan mata, dan masih bisa disunting lagi.
 * Tidak ada yang tersimpan sampai formulirnya dikirim.
 */
watch(() => form.business_type, (businessType) => {
    form.features = [...(props.featurePresets[businessType] ?? props.defaultFeatures)];
});

const toggleFeature = (name) => {
    form.features = form.features.includes(name)
        ? form.features.filter((feature) => feature !== name)
        : [...form.features, name];
};

const showPassword = ref(false);

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Daftar" />

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- Mobile brand strip -->
        <div
            class="md:hidden flex-shrink-0 h-12 bg-primary flex items-center px-6 gap-2"
            aria-hidden="true"
        >
            <span class="text-primary-foreground font-bold text-lg tracking-tight leading-none">SAPI</span>
            <span class="text-primary-foreground/40 text-[0.65rem] font-semibold uppercase tracking-widest mt-px">POS</span>
        </div>

        <!-- Brand panel — desktop only -->
        <div
            class="hidden md:flex md:w-[400px] lg:w-[460px] flex-shrink-0 bg-primary relative overflow-hidden flex-col justify-between p-10 lg:p-12"
            aria-hidden="true"
        >
            <!-- Watermark letterform — purely decorative depth -->
            <span
                class="absolute -bottom-24 -right-10 text-[20rem] font-black leading-none tracking-tighter
                       text-primary-foreground/[0.055] select-none pointer-events-none"
            >S</span>

            <!-- Identity block -->
            <div class="relative z-10">
                <div class="flex items-baseline gap-2">
                    <span class="text-[1.625rem] font-bold text-primary-foreground tracking-tight leading-none">
                        SAPI
                    </span>
                    <span class="text-[0.65rem] font-semibold text-primary-foreground/40 uppercase tracking-widest">
                        POS
                    </span>
                </div>
                <p class="mt-4 text-[0.9375rem] text-primary-foreground/70 leading-relaxed max-w-[200px]">
                    Kelola kasir, stok, dan laporan dari satu tempat.
                </p>
            </div>

            <!-- Footer caption -->
            <p class="relative z-10 text-xs text-primary-foreground/28 leading-relaxed">
                Untuk warung dan restoran Indonesia
            </p>
        </div>

        <!-- Form panel -->
        <main class="flex-1 flex items-center justify-center bg-background px-6 py-14 md:py-0">
            <div class="w-full max-w-sm">

                <!-- Form heading -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-foreground tracking-tight">Daftar</h1>
                    <p class="text-sm text-muted-foreground mt-1.5 leading-relaxed">
                        Buat akun untuk mulai mengelola usaha Anda
                    </p>
                </div>

                <form @submit.prevent="submit" novalidate>

                    <!-- Business Name -->
                    <div>
                        <label
                            for="register-business-name"
                            class="block text-sm font-medium text-foreground mb-1.5"
                        >
                            Nama Usaha
                        </label>
                        <input
                            id="register-business-name"
                            v-model="form.business_name"
                            type="text"
                            autocomplete="organization"
                            autofocus
                            placeholder="Warung Sapi"
                            :aria-invalid="!!form.errors.business_name"
                            aria-describedby="business-name-error"
                            :class="[
                                'w-full px-3 py-2.5 bg-card text-sm text-foreground',
                                'placeholder:text-muted-foreground border rounded-lg',
                                'transition-colors duration-150',
                                'focus:outline-none focus:ring-2 focus:ring-offset-1',
                                form.errors.business_name
                                    ? 'border-destructive focus:ring-destructive/50'
                                    : 'border-border hover:border-muted-foreground/35 focus:ring-ring'
                            ]"
                        />
                        <p
                            v-if="form.errors.business_name"
                            id="business-name-error"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.business_name }}
                        </p>
                    </div>

                    <!-- Business Type -->
                    <div class="mt-5">
                        <label
                            for="register-business-type"
                            class="block text-sm font-medium text-foreground mb-1.5"
                        >
                            Jenis Usaha
                            <span class="font-normal text-muted-foreground">(opsional)</span>
                        </label>
                        <!--
                            `[BL-079]` opsi (iii). Kolom ini TETAP opsional —
                            mewajibkannya menaikkan gesekan pendaftaran demi
                            dimensi harga yang belum dipakai satu aturan pun.
                            Yang dicabut adalah tebakannya: "Belum ditentukan"
                            dulu diam-diam tersimpan sebagai `lainnya`, jadi
                            sebagian tenant bisa masuk kelompok tarif lewat
                            jawaban yang tak pernah mereka berikan. Sekarang
                            layarnya menyebut hasilnya, dan menyebut kenapa
                            pertanyaannya diajukan.
                        -->
                        <p class="text-xs text-muted-foreground mb-1.5">
                            Dipakai menyiapkan fitur awal, dan kelak bisa ikut menentukan tarif langganan Anda. Bisa diubah kapan saja lewat Pengaturan.
                        </p>
                        <select
                            id="register-business-type"
                            v-model="form.business_type"
                            :aria-invalid="!!form.errors.business_type"
                            aria-describedby="business-type-error"
                            :class="[
                                'w-full px-3 py-2.5 bg-card text-sm text-foreground',
                                'border rounded-lg transition-colors duration-150',
                                'focus:outline-none focus:ring-2 focus:ring-offset-1',
                                form.errors.business_type
                                    ? 'border-destructive focus:ring-destructive/50'
                                    : 'border-border hover:border-muted-foreground/35 focus:ring-ring'
                            ]"
                        >
                            <option value="">Belum yakin — disamakan dengan "Lainnya"</option>
                            <option v-for="(label, value) in businessTypes" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.business_type"
                            id="business-type-error"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.business_type }}
                        </p>
                    </div>

                    <!-- Fitur awal — diisi preset jenis usaha, tetap bisa diubah -->
                    <fieldset v-if="featureCatalog.length" class="mt-5">
                        <legend class="block text-sm font-medium text-foreground mb-1.5">
                            Fitur yang Menyala
                        </legend>
                        <p class="text-xs text-muted-foreground leading-relaxed mb-2.5">
                            Disesuaikan dengan jenis usaha Anda. Ubah sesuka Anda sekarang, atau
                            nanti lewat Pengaturan &rarr; Cara Kerja Sistem.
                        </p>

                        <div class="border border-border rounded-lg divide-y divide-border overflow-hidden">
                            <label
                                v-for="feature in featureCatalog"
                                :key="feature.name"
                                :for="`register-feature-${feature.name}`"
                                class="flex items-start gap-3 p-3 bg-card cursor-pointer
                                       transition-colors duration-150 hover:bg-muted/40"
                            >
                                <input
                                    :id="`register-feature-${feature.name}`"
                                    type="checkbox"
                                    :value="feature.name"
                                    :checked="form.features.includes(feature.name)"
                                    class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-border
                                           text-primary focus:outline-none focus:ring-2 focus:ring-ring
                                           focus:ring-offset-1"
                                    @change="toggleFeature(feature.name)"
                                />
                                <span class="min-w-0">
                                    <span class="block text-sm text-foreground leading-snug">
                                        {{ feature.label }}
                                    </span>
                                    <span class="block text-xs text-muted-foreground leading-relaxed mt-0.5">
                                        {{ feature.description }}
                                    </span>
                                </span>
                            </label>
                        </div>

                        <p
                            v-if="form.errors.features"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.features }}
                        </p>
                    </fieldset>

                    <!-- Owner Name -->
                    <div class="mt-5">
                        <label
                            for="register-name"
                            class="block text-sm font-medium text-foreground mb-1.5"
                        >
                            Nama Pemilik
                        </label>
                        <input
                            id="register-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            placeholder="Nama Anda"
                            :aria-invalid="!!form.errors.name"
                            aria-describedby="name-error"
                            :class="[
                                'w-full px-3 py-2.5 bg-card text-sm text-foreground',
                                'placeholder:text-muted-foreground border rounded-lg',
                                'transition-colors duration-150',
                                'focus:outline-none focus:ring-2 focus:ring-offset-1',
                                form.errors.name
                                    ? 'border-destructive focus:ring-destructive/50'
                                    : 'border-border hover:border-muted-foreground/35 focus:ring-ring'
                            ]"
                        />
                        <p
                            v-if="form.errors.name"
                            id="name-error"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div class="mt-5">
                        <label
                            for="register-email"
                            class="block text-sm font-medium text-foreground mb-1.5"
                        >
                            Email
                        </label>
                        <input
                            id="register-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="nama@usaha.com"
                            :aria-invalid="!!form.errors.email"
                            aria-describedby="email-error"
                            :class="[
                                'w-full px-3 py-2.5 bg-card text-sm text-foreground',
                                'placeholder:text-muted-foreground border rounded-lg',
                                'transition-colors duration-150',
                                'focus:outline-none focus:ring-2 focus:ring-offset-1',
                                form.errors.email
                                    ? 'border-destructive focus:ring-destructive/50'
                                    : 'border-border hover:border-muted-foreground/35 focus:ring-ring'
                            ]"
                        />
                        <p
                            v-if="form.errors.email"
                            id="email-error"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div class="mt-5">
                        <label
                            for="register-password"
                            class="block text-sm font-medium text-foreground mb-1.5"
                        >
                            Kata Sandi
                        </label>
                        <div class="relative">
                            <input
                                id="register-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                :aria-invalid="!!form.errors.password"
                                aria-describedby="password-error"
                                :class="[
                                    'w-full px-3 py-2.5 pr-10 bg-card text-sm text-foreground',
                                    'border rounded-lg transition-colors duration-150',
                                    'focus:outline-none focus:ring-2 focus:ring-offset-1',
                                    form.errors.password
                                        ? 'border-destructive focus:ring-destructive/50'
                                        : 'border-border hover:border-muted-foreground/35 focus:ring-ring'
                                ]"
                            />
                            <!-- Show/hide toggle -->
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3
                                       text-muted-foreground hover:text-foreground transition-colors duration-150
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-r-lg"
                                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                            >
                                <!-- Eye slash — password hidden -->
                                <svg
                                    v-if="!showPassword"
                                    class="w-[1.0625rem] h-[1.0625rem]"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                          d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5
                                             c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5
                                             c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774
                                             M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21
                                             m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <!-- Eye open — password visible -->
                                <svg
                                    v-else
                                    class="w-[1.0625rem] h-[1.0625rem]"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                          d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5
                                             c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639
                                             C20.577 16.49 16.64 19.5 12 19.5
                                             c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        <p
                            v-if="form.errors.password"
                            id="password-error"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Password Confirmation -->
                    <div class="mt-5">
                        <label
                            for="register-password-confirmation"
                            class="block text-sm font-medium text-foreground mb-1.5"
                        >
                            Konfirmasi Kata Sandi
                        </label>
                        <input
                            id="register-password-confirmation"
                            v-model="form.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            :aria-invalid="!!form.errors.password_confirmation"
                            aria-describedby="password-confirmation-error"
                            :class="[
                                'w-full px-3 py-2.5 bg-card text-sm text-foreground',
                                'border rounded-lg transition-colors duration-150',
                                'focus:outline-none focus:ring-2 focus:ring-offset-1',
                                form.errors.password_confirmation
                                    ? 'border-destructive focus:ring-destructive/50'
                                    : 'border-border hover:border-muted-foreground/35 focus:ring-ring'
                            ]"
                        />
                        <p
                            v-if="form.errors.password_confirmation"
                            id="password-confirmation-error"
                            role="alert"
                            class="mt-1.5 text-xs text-destructive"
                        >
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>

                    <!--
                        Pemberitahuan masa gratis — sengaja di sini, tepat di
                        atas tombolnya, karena inilah titik keputusannya
                        (`[BL-071]`).
                    -->
                    <div
                        class="mt-7 rounded-lg border border-border bg-muted/40 px-4 py-3"
                    >
                        <p class="text-sm font-medium text-foreground">{{ trialNotice.headline }}</p>
                        <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                            {{ trialNotice.body }}
                        </p>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-4 flex items-center justify-center gap-2
                               px-4 py-2.5 bg-primary text-primary-foreground
                               text-sm font-semibold rounded-lg
                               hover:bg-primary/90 active:bg-primary/80
                               transition-colors duration-150
                               focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2
                               disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg
                            v-if="form.processing"
                            class="animate-spin w-4 h-4 shrink-0"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"
                            />
                            <path
                                class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0
                                   014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        <span>{{ form.processing ? 'Memproses...' : 'Daftar' }}</span>
                    </button>

                </form>

                <!-- Link to Login -->
                <p class="mt-6 text-center text-sm text-muted-foreground">
                    Sudah punya akun?
                    <Link
                        href="/login"
                        class="font-medium text-primary hover:text-primary/80 transition-colors duration-150"
                    >
                        Masuk
                    </Link>
                </p>
            </div>
        </main>

    </div>
</template>
