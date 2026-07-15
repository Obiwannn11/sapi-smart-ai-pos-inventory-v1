<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';

const form = useForm({
    business_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

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

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-7 flex items-center justify-center gap-2
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
