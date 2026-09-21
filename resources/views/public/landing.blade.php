<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPI - Smart AI POS & Inventory untuk UMKM</title>
    <meta name="description" content="Aplikasi Kasir (POS) cerdas dengan AI. Dilengkapi prediksi stok personal dan asisten finansial otomatis tanpa perlu bayar konsultan mahal.">

    @include('public.partials.favicon')

    <!-- Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css'])

    @include('public.partials.theme')

    <style>
        .glass-nav {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .hero-pill {
            background: linear-gradient(90deg, var(--primary) 0%, var(--brand) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bg-pill {
            background: color-mix(in oklch, var(--primary) 12%, transparent);
            padding: 0.2em 0.6em;
            border-radius: 9999px;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        .float-animation-delay {
            animation: float 4s ease-in-out infinite;
            animation-delay: 1s;
        }

        /* Scroll Reveal Animations */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease-out;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 0.8s ease-out;
        }
        .reveal-left.active {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-right {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.8s ease-out;
        }
        .reveal-right.active {
            opacity: 1;
            transform: translateX(0);
        }
        .stagger-1 { transition-delay: 0.1s; }
        .stagger-2 { transition-delay: 0.2s; }
        .stagger-3 { transition-delay: 0.3s; }
        .stagger-4 { transition-delay: 0.4s; }

        .demo-tab { background: white; color: #6b7280; border: 2px solid #f3f4f6; }
        .demo-tab:hover { border-color: color-mix(in oklch, var(--primary) 35%, white); color: var(--primary); }
        .active-tab { background: var(--primary) !important; color: white !important; border-color: var(--primary) !important; box-shadow: 0 10px 30px color-mix(in oklch, var(--primary) 20%, transparent); }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans selection:bg-primary/10 selection:text-primary antialiased overflow-x-hidden">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 glass-nav border-b border-gray-100" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="flex justify-between h-20 items-center">
                <!-- Left: Brand Name -->
                <div class="flex-shrink-0">
                    <a href="/" class="flex items-center gap-3 group">
                        @include('public.partials.wordmark', ['size' => 'md', 'interactive' => true])
                    </a>
                </div>

                <!-- Right: Links & Action -->
                <div class="flex items-center gap-4 md:gap-10">
                    <div class="hidden md:flex items-center gap-8">
                        <a href="#solusi" class="text-[14px] font-bold text-gray-500 hover:text-primary transition-all">Solusi</a>
                        <a href="#fitur" class="text-[14px] font-bold text-gray-500 hover:text-primary transition-all">Fitur AI</a>
                        <a href="#demo" class="text-[14px] font-bold text-gray-500 hover:text-primary transition-all">Demo</a>
                        <a href="#pricing" class="text-[14px] font-bold text-gray-500 hover:text-primary transition-all">Harga</a>
                        <a href="{{ route('docs.index') }}" class="text-[14px] font-bold text-gray-500 hover:text-primary transition-all">Dokumentasi</a>
                    </div>
                    <div class="h-6 w-px bg-gray-100 hidden md:block"></div>
                    <a href="/login" class="hidden sm:block bg-primary text-white px-8 py-3 rounded-full text-[14px] font-black hover:bg-primary/90 transition-all shadow-xl shadow-primary/10 hover:shadow-primary/20 hover:-translate-y-0.5">
                        Login
                    </a>

                    <!-- Hamburger Button -->
                    <button @click="open = !open" class="md:hidden p-2 text-gray-600 hover:text-primary transition-colors">
                        <svg x-show="!open" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <svg x-show="open" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-4"
             class="md:hidden bg-white border-b border-gray-100 shadow-2xl absolute w-full px-6 py-8 space-y-6">
            <a @click="open = false" href="#solusi" class="block text-lg font-black text-gray-900 hover:text-primary">Solusi</a>
            <a @click="open = false" href="#fitur" class="block text-lg font-black text-gray-900 hover:text-primary">Fitur AI</a>
            <a @click="open = false" href="#demo" class="block text-lg font-black text-gray-900 hover:text-primary">Demo</a>
            <a @click="open = false" href="#pricing" class="block text-lg font-black text-gray-900 hover:text-primary">Harga</a>
            <a @click="open = false" href="{{ route('docs.index') }}" class="block text-lg font-black text-gray-900 hover:text-primary">Dokumentasi</a>
            <div class="pt-4 border-t border-gray-100 space-y-3">
                {{--
                    Dulu satu tombol "Login / Daftar Gratis" yang menuju
                    `/login` saja. Dipecah dua karena keduanya memang dua
                    tujuan, dan labelnya menyebut lama masa gratisnya
                    (`[BL-071]`) alih-alih "gratis" yang menyembunyikan bahwa
                    ia berakhir.
                --}}
                <a @click="open = false" href="{{ route('register') }}" class="block w-full bg-primary text-white text-center py-4 rounded-2xl font-black shadow-xl shadow-primary/10">Coba Gratis {{ $pricing['trial_months'] }} Bulan</a>
                <a @click="open = false" href="/login" class="block w-full text-center py-3 text-gray-900 font-black">Masuk</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-36 pb-24 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="grid lg:grid-cols-12 gap-16 items-center">
                <div class="lg:col-span-6 reveal-left">

                    <h1 class="text-4xl sm:text-5xl lg:text-7xl font-black tracking-tight text-gray-900 mb-8 leading-[1.15] max-w-xl lg:max-w-2xl">
                        Kelola Toko Jadi <br class="hidden sm:block">
                        <span class="bg-pill text-primary">Lebih Pintar</span> <br class="hidden sm:block">
                        Dengan AI.
                    </h1>
                    <p class="text-lg sm:text-xl text-gray-600 mb-10 leading-relaxed max-w-lg font-medium">
                        SAPI menandai stok yang menipis dan barang yang berhenti laku, menganalisis pola penjualan Anda, dan menurunkan saran jual yang menyebut barangnya.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 sm:gap-5">
                        {{--
                            "Daftar Gratis" tidak salah — dua bulan memang
                            gratis — tapi ia menyembunyikan bagian yang paling
                            menentukan: masa itu berakhir dengan perpindahan ke
                            paket berbayar. Lamanya dibacakan dari config lewat
                            `PublicPricing`, tidak diketik di sini (`[BL-071]`).
                        --}}
                        <a href="{{ route('register') }}" class="px-8 sm:px-10 py-4 sm:py-5 bg-primary text-white rounded-[1.2rem] sm:rounded-[1.5rem] font-extrabold text-base sm:text-lg hover:bg-primary/90 shadow-2xl shadow-primary/20 transition-all transform hover:-translate-y-1 text-center">
                            Coba Gratis {{ $pricing['trial_months'] }} Bulan
                        </a>
                        <a href="#fitur" class="px-8 sm:px-10 py-4 sm:py-5 bg-white text-gray-900 border-2 border-gray-100 rounded-[1.2rem] sm:rounded-[1.5rem] font-extrabold text-base sm:text-lg hover:border-gray-200 transition-all text-center">
                            Lihat Fitur
                        </a>
                    </div>
                </div>
                <div class="lg:col-span-6 relative reveal-right">
                    <!-- Main Mockup -->
                    <div class="relative z-10 bg-gray-50 rounded-[3rem] p-4 border border-gray-100 shadow-2xl">
                        <img src="{{ asset('Dashboard-owner.webp') }}" alt="Dashboard pemilik SAPI" width="2160" height="1350" class="rounded-[2.5rem] w-full">
                    </div>

                    <!-- Floating Elements -->
                    {{--
                        Kartu ini dulu berbunyi "Prediksi Stok — Aman Hingga 14
                        Hari", dan itu menyebut horizon yang tidak pernah
                        dihitung di mana pun: `BadgeHelperService` membandingkan
                        ambang tetap, bukan meramal. Kalimatnya sekarang meminjam
                        pesan badge `low_stock` apa adanya — "{n} varian mendekati
                        habis" — dan warnanya mengikuti `severity: warning` yang
                        sama, supaya yang dijanjikan di sini persis yang dilihat
                        orang setelah masuk (`[BL-083]`).
                    --}}
                    <div class="absolute -top-10 -right-5 z-20 float-animation hidden sm:block">
                        <div class="bg-white p-5 rounded-3xl shadow-2xl border border-gray-100 flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-100 text-amber-500 rounded-2xl flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase">Stok Kritis</p>
                                <p class="text-sm font-black text-gray-900">3 varian mendekati habis</p>
                            </div>
                        </div>
                    </div>

                    <div class="absolute bottom-10 -left-10 z-20 float-animation-delay hidden sm:block">
                        <div class="bg-white p-6 rounded-3xl shadow-2xl border border-gray-100">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-3 h-3 bg-primary rounded-full"></div>
                                <p class="text-sm font-black text-gray-900">Badge Helper</p>
                            </div>
                            <p class="text-sm text-gray-600 font-medium leading-tight">
                                "Kopi Susu Gula Aren mulai <br> sepi, beri diskon 15%?"
                            </p>
                            {{--
                                Dulu sebuah tombol "Eksekusi Sekarang". Tidak ada
                                jalan satu tekan dari saran ke diskon terpasang:
                                `BadgeCard.vue` hanya membuka-tutup, dan aturan
                                diskon punya layarnya sendiri. Diturunkan jadi
                                keterangan tempat, bukan janji tindakan
                                (`[BL-083]` butir (b)).
                            --}}
                            <p class="mt-4 text-xs font-black text-gray-400 uppercase tracking-wide">Muncul di dashboard Anda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problem Section -->
    <section id="solusi" class="py-24 lg:py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <h2 class="text-primary font-black tracking-widest uppercase text-sm mb-4">Tantangan Nyata UMKM</h2>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">
                    Hambatan yang Memperlambat <br> Pertumbuhan Bisnis Anda
                </h3>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Problem 1 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-1">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Menebak-nebak Stok</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Terlalu banyak stok yang tidak laku (Dead Stock) atau kehabisan barang saat sedang ramai. Modal tertimbun di tempat yang salah.
                    </p>
                </div>

                <!-- Problem 2 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-2">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Grafik Kosong Tanpa Arti</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Aplikasi kasir biasa cuma kasih grafik. Tapi apa artinya? Anda tetap bingung langkah apa yang harus diambil hari ini.
                    </p>
                </div>

                <!-- Problem 3 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-3">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Data Tersebar</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Rekapan manual yang sering salah hitung. Antara stok di gudang dan laporan penjualan sering tidak nyambung.
                    </p>
                </div>

                <!-- Problem 4 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-4">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.347.09-.411 1.307A1 1 0 0113 18H11a1 1 0 01-.98-.804l-.412-1.307-.346-.09z" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Keputusan Tanpa Panduan Aksi</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Data transaksi sudah ada, tapi tidak ada yang memberitahu langkah konkret berikutnya. Anda harus menebak sendiri promosi apa yang harus dijalankan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Section -->
    <section class="py-24 lg:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="grid lg:grid-cols-2 gap-10 items-stretch">
                <!-- Dulu -->
                <div class="bg-red-50/50 p-12 rounded-[3rem] border border-red-100 reveal-left">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-red-100 text-red-600 text-xs font-black uppercase mb-8">
                        Cara Lama
                    </div>
                    <h4 class="text-3xl font-black text-gray-900 mb-10">Manual & Menebak</h4>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-red-200 text-red-600 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                            </div>
                            <p class="text-lg text-gray-600 font-medium italic">"Kayaknya barang ini masih ada di gudang..."</p>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-red-200 text-red-600 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                            </div>
                            <p class="text-lg text-gray-600 font-medium italic">"Kenapa ya bulan ini rugi? Perasaan rame."</p>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-red-200 text-red-600 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                            </div>
                            <p class="text-lg text-gray-600 font-medium italic">"Aduh, barang ini udah expired tapi belum terjual."</p>
                        </li>
                    </ul>
                </div>

                <!-- Sekarang -->
                <div class="bg-primary p-12 rounded-[3rem] text-white shadow-2xl shadow-primary/20 reveal-right">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-brand text-white text-xs font-black uppercase mb-8">
                        Cara SAPI
                    </div>
                    <h4 class="text-3xl font-black mb-10 text-white">Otomatis & Pintar</h4>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-brand text-white rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <p class="text-lg font-bold">"3 varian mendekati habis, ini daftarnya."</p>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-brand text-white rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <p class="text-lg font-bold">"Laba bersih bulan ini sudah terhitung, bukan dikira-kira."</p>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-brand text-white rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <p class="text-lg font-bold">"Saran jual kemarin: 12 ditawarkan, 5 diterima."</p>
                        </li>
                    </ul>
                    <div class="mt-12 text-center md:text-left">
                        <a href="{{ route('register') }}" class="inline-block px-8 py-4 bg-white text-primary rounded-2xl font-black hover:bg-primary/5 transition-all">Ganti ke SAPI Sekarang</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tabbed Features Section -->
    <section id="fitur" class="py-24 lg:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">Fitur yang Sudah <span class="text-primary">Jalan Hari Ini</span></h3>
            </div>

            <div class="bg-gray-50 rounded-[4rem] p-10 lg:p-16 border border-gray-100 reveal">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div class="order-2 lg:order-1">
                        <div class="space-y-8">
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-xs reveal stagger-1">
                                <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Kasir & Sesi Kas</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Split bill, banyak metode bayar, dan modifier per item. Tagihan terbuka menunggu di topbar kasir sampai dilunasi,
                                    dan tiap shift dibuka-tutup lewat sesi kas dengan ringkasan selisihnya.
                                </p>
                            </div>
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-xs reveal stagger-2">
                                <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Analisis AI & Saran Jual</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Analisis penjualan yang bisa dibaca, dengan jatah harian yang selalu terlihat sisanya.
                                    Stok yang menumpuk berubah jadi saran jual di layar kasir — dan kunci API Anda sendiri melepas batasnya.
                                </p>
                            </div>
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-xs reveal stagger-3">
                                <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Stok, Antrian, & Peran</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Stok per varian dengan riwayat pergerakan dan opname. Papan antrian dapur untuk yang memasak per pesanan.
                                    Tiap staf hanya membuka modul yang memang haknya.
                                </p>
                            </div>

                            {{--
                                Kapabilitas yang nyata tapi BUKAN halaman siap
                                pakai: pesan mandiri dan MCP berbentuk API, dan
                                POS mobile punya referensinya sendiri. Disebut
                                apa adanya sebagai antarmuka program — memajangnya
                                seolah layar yang tinggal dibuka persis jenis
                                karangan yang dibersihkan `[BL-032]`.
                            --}}
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-xs reveal stagger-4">
                                <div class="w-12 h-12 bg-primary/5 text-primary rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Terbuka lewat API</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Pesan mandiri, POS mobile, dan asisten AI Anda sendiri lewat MCP — semuanya berbentuk API, bukan layar bawaan,
                                    dan <a href="{{ route('api-docs') }}" class="text-primary hover:underline">referensinya terbuka</a> untuk dibaca sebelum Anda memutuskan.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="relative order-1 lg:order-2 reveal stagger-2">
                        <div class="sticky top-32">
                            <div class="relative">
                                <div class="absolute -inset-4 bg-primary/10 rounded-[3rem] rotate-3 opacity-50"></div>
                                <div class="relative bg-white rounded-[2.5rem] p-4 shadow-2xl border border-gray-100 transform -rotate-2 transition-transform hover:rotate-0 duration-500">
                                    <img src="{{ asset('POS-Interface.webp') }}" alt="Layar kasir SAPI dengan keranjang berisi" width="2160" height="1350" loading="lazy" class="rounded-[2rem] w-full shadow-inner">
                                    <div class="absolute -bottom-6 -right-6 bg-primary text-white px-6 py-3 rounded-2xl font-black text-sm shadow-xl">
                                        Layar Kasir
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Evidence Gallery Section -->
    <section class="py-24 lg:py-32 bg-gray-50 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900">Layar yang Dipakai Sehari-hari</h3>
                <p class="text-lg text-gray-600 font-medium mt-4">Diambil dari aplikasi yang berjalan, bukan gambar rancangan.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Report -->
                <div class="group reveal stagger-1">
                    <div class="bg-white rounded-[2.5rem] p-4 shadow-lg border border-gray-100 transition-all group-hover:-translate-y-2 group-hover:shadow-2xl">
                        <div class="relative rounded-[1.5rem] overflow-hidden mb-6 aspect-video">
                            <img src="{{ asset('Reports-Daily.webp') }}" alt="Laporan Harian" width="2160" height="1350" loading="lazy" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <h4 class="text-xl font-black text-gray-900 px-2">Laporan Harian</h4>
                        <p class="text-sm text-gray-500 font-bold px-2 mt-2">Omzet, laba, dan rekap metode bayar hari itu.</p>
                    </div>
                </div>

                <!-- Stock -->
                <div class="group reveal stagger-2">
                    <div class="bg-white rounded-[2.5rem] p-4 shadow-lg border border-gray-100 transition-all group-hover:-translate-y-2 group-hover:shadow-2xl">
                        <div class="relative rounded-[1.5rem] overflow-hidden mb-6 aspect-video">
                            <img src="{{ asset('Stock-Management.webp') }}" alt="Manajemen Stok" width="2160" height="1350" loading="lazy" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <h4 class="text-xl font-black text-gray-900 px-2">Manajemen Stok</h4>
                        <p class="text-sm text-gray-500 font-bold px-2 mt-2">Tiap pergerakan stok tercatat beserta alasannya.</p>
                    </div>
                </div>

                <!-- Product -->
                <div class="group reveal stagger-3">
                    <div class="bg-white rounded-[2.5rem] p-4 shadow-lg border border-gray-100 transition-all group-hover:-translate-y-2 group-hover:shadow-2xl">
                        <div class="relative rounded-[1.5rem] overflow-hidden mb-6 aspect-video">
                            <img src="{{ asset('Product-List.webp') }}" alt="Daftar Produk" width="2160" height="1350" loading="lazy" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <h4 class="text-xl font-black text-gray-900 px-2">Katalog Produk</h4>
                        <p class="text-sm text-gray-500 font-bold px-2 mt-2">Produk, varian, dan modifier dalam satu tempat.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DEMO SIMULATION SECTION -->
    <section id="demo" class="py-24 lg:py-32 bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/5 border border-primary/15 text-primary text-sm font-bold mb-6">
                    <span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span>
                    Coba Langsung — Tanpa Daftar
                </div>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900">Rasakan Cara Kerja SAPI</h3>
                <p class="text-lg text-gray-600 font-medium mt-4">Simulasi interaktif tiga fitur utama. Klik, tambah produk, dan lihat AI bekerja.</p>
            </div>

            <!-- Tab Navigation -->
            <div class="flex flex-wrap justify-center gap-4 mb-10 reveal stagger-1">
                <button onclick="showTab('pos')" id="tab-pos" class="demo-tab active-tab px-8 py-3 rounded-2xl font-black text-base transition-all">Simulasi Kasir</button>
                <button onclick="showTab('badge')" id="tab-badge" class="demo-tab px-8 py-3 rounded-2xl font-black text-base transition-all">Badge Helper AI</button>
                <button onclick="showTab('upsell')" id="tab-upsell" class="demo-tab px-8 py-3 rounded-2xl font-black text-base transition-all">Saran Jual</button>
            </div>

            <!-- TAB 1: POS KASIR -->
            <div id="panel-pos" class="demo-panel reveal stagger-2">
                <div class="bg-white rounded-[3rem] shadow-xl border border-gray-100 overflow-hidden">
                    <div class="bg-primary px-8 py-5 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center text-white font-black">S</div>
                            <span class="text-white font-black text-lg">SAPI — Mode Kasir</span>
                        </div>
                        <div class="text-primary/40 font-bold text-sm" id="pos-clock"></div>
                    </div>
                    <div class="grid lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">
                        <div class="lg:col-span-7 p-8">
                            <p class="font-black text-gray-400 uppercase text-xs tracking-widest mb-5">Pilih Produk</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4" id="product-grid"></div>
                        </div>
                        <div class="lg:col-span-5 p-8 flex flex-col">
                            <p class="font-black text-gray-400 uppercase text-xs tracking-widest mb-5">Pesanan</p>
                            <div id="cart-items" class="flex-1 space-y-3 min-h-[200px]"></div>
                            <div class="border-t border-gray-100 pt-6 mt-4">
                                <div class="flex justify-between items-center mb-6">
                                    <span class="font-black text-gray-900 text-xl">Total</span>
                                    <span id="pos-total" class="font-black text-primary text-2xl">Rp 0</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <button onclick="clearCart()" class="py-4 bg-gray-100 text-gray-700 rounded-2xl font-black hover:bg-gray-200 transition-all">Batal</button>
                                    <button onclick="checkout()" class="py-4 bg-primary text-white rounded-2xl font-black hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">Bayar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: BADGE HELPER -->
            <div id="panel-badge" class="demo-panel hidden">
                <div class="bg-white rounded-[3rem] shadow-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-900 px-8 py-5 flex items-center justify-between">
                        <span class="text-white font-black text-lg">SAPI Badge Helper AI</span>
                    </div>
                    <div class="p-8 lg:p-12">
                        <div class="space-y-5" id="badge-list"></div>
                        <div class="mt-10 text-center">
                            <button onclick="refreshBadges()" class="px-8 py-4 bg-gray-900 text-white rounded-2xl font-black hover:bg-gray-800 transition-all">Refresh Analisis AI</button>
                        </div>
                    </div>
                </div>
            </div>

            {{--
                Tab ini dulu "Prediksi Stok (Machine Learning)" dan memperagakan
                sisa hari per produk. Tidak ada model, tidak ada pustaka ML, dan
                tidak ada satu pun perhitungan horizon di basis kode. Diganti
                Saran Jual, yang sungguh ada dan justru belum punya peragaan
                (`[BL-089]`). Isinya mengikuti bentuk `Suggestion`: label, catatan
                alasan, dan tambahan rupiah — dengan kode alasan yang memang
                dipakai `UpsellEvent`.
            --}}
            <!-- TAB 3: SARAN JUAL -->
            <div id="panel-upsell" class="demo-panel hidden">
                <div class="bg-white rounded-[3rem] shadow-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-900 px-8 py-5 flex items-center justify-between">
                        <span class="text-white font-black text-lg">Saran Jual</span>
                        <span class="text-white/50 font-bold text-xs uppercase tracking-widest">Muncul di layar kasir</span>
                    </div>
                    <div class="p-8 lg:p-12">
                        <p class="text-gray-600 font-medium leading-relaxed mb-8">
                            Saat kasir menambahkan barang, SAPI menurunkan saran yang menyebut barangnya — bukan grafik yang harus ditafsirkan sendiri. Keranjang di bawah berisi <span class="font-black text-gray-900">Espresso - Single</span>.
                        </p>
                        <div class="space-y-4" id="upsell-list"></div>
                        <p class="mt-8 text-xs text-gray-400 font-bold text-center">
                            Owner bisa menambahkan aturannya sendiri, dan aturan owner selalu menang slot.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Onboarding Flow Section -->
    <section class="py-24 lg:py-32 bg-gray-50 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/5 text-primary rounded-full text-xs font-black uppercase tracking-widest mb-6">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg>
                    Mulai Cepat
                </div>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">Tiga Langkah sampai <span class="text-primary">Kasir Siap Dipakai</span></h3>
                <p class="text-lg text-gray-600 font-medium mt-6">Daftar, susun katalog, buka sesi kas. Tidak ada yang disiapkan diam-diam untuk Anda.</p>
            </div>

            <div class="relative">
                <!-- Vertical Line -->
                <div class="absolute left-1/2 top-0 bottom-0 w-1 bg-gray-100 -translate-x-1/2 hidden lg:block"></div>

                <div class="space-y-24 lg:space-y-0">
                    <!-- Step 1 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal">
                        <div class="lg:text-right lg:pr-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-primary text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-primary/20">1</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Daftar dengan Email</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md lg:ml-auto">Empat isian: nama usaha, jenis usaha, email, dan kata sandi. Verifikasi emailnya, lalu masuk.</p>
                        </div>
                        <div class="lg:pl-24 bg-white rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-xs group transition-all hover:bg-primary/5">
                            <div class="bg-white p-6 rounded-2xl shadow-xl border border-gray-100 flex flex-col items-center gap-4">
                                <div class="w-full space-y-2">
                                    <div class="h-10 bg-gray-50 rounded-lg w-full"></div>
                                    <div class="h-10 bg-primary rounded-lg w-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal pt-24 lg:pt-32">
                        <div class="lg:order-2 lg:pl-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-primary text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-primary/20">2</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Masa Gratis Langsung Berjalan</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md">Dua bulan, semua fitur terbuka, tanpa kartu kredit. Sesudahnya akun berpindah sendiri ke paket berbayar — tidak berhenti mendadak.</p>
                        </div>
                        <div class="lg:order-1 lg:pr-24 bg-white rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-xs group transition-all hover:bg-primary/5">
                            <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100 space-y-4">
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase mb-2 block">Nama Toko</label>
                                    <div class="h-12 border-2 border-primary/15 rounded-xl flex items-center px-4 font-bold text-gray-700">Kopi Nusantara</div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase mb-2 block">Kategori</label>
                                    <div class="h-12 border-2 border-primary/15 rounded-xl flex items-center px-4 font-bold text-gray-700 justify-between">
                                        Coffee Shop
                                        <svg class="w-4 h-4 text-primary/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal pt-24 lg:pt-32">
                        <div class="lg:text-right lg:pr-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-primary text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-primary/20">3</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Susun Katalog Anda</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md lg:ml-auto">Produk, varian, dan modifier disusun sendiri lewat halaman Katalog — sesuai menu yang benar-benar Anda jual, bukan tebakan.</p>
                        </div>
                        <div class="lg:pl-24 bg-white rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-xs group transition-all hover:bg-primary/5">
                            <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100 flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-primary/5 text-primary rounded-full flex items-center justify-center mb-4 animate-pulse">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                </div>
                                <h5 class="font-black text-gray-900 mb-1 text-sm">Menyiapkan Toko...</h5>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Katalog · Produk & Varian</p>
                                <div class="w-full bg-gray-100 h-1.5 rounded-full mt-6 overflow-hidden">
                                    <div class="bg-primary h-full w-2/3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal pt-24 lg:pt-32">
                        <div class="lg:order-2 lg:pl-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-primary text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-primary/20">4</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Siap Digunakan!</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md">Buka sesi kas, dan kasir siap dipakai. Laporan harian, rekap bulanan, dan analisis AI mengikuti dari transaksi yang masuk.</p>
                        </div>
                        <div class="lg:order-1 lg:pr-24 bg-white rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-xs group transition-all hover:bg-primary/5">
                            <div class="bg-white p-3 rounded-2xl shadow-xl border border-gray-100 overflow-hidden relative">
                                <img src="{{ asset('POS-Interface.webp') }}" alt="" width="2160" height="1350" loading="lazy" class="w-full opacity-30 blur-[2px] rounded-lg">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="bg-white px-8 py-4 rounded-2xl shadow-2xl border border-primary/15 flex items-center gap-4 animate-bounce">
                                        <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <span class="font-black text-gray-900">Sistem Siap!</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device Support Bar -->
            <div class="mt-16 bg-white rounded-3xl p-8 border border-gray-100 flex flex-col md:flex-row items-center justify-between gap-8 reveal">
                <div class="text-center md:text-left">
                    <h5 class="text-lg font-black text-gray-900 mb-1">Akses dari Perangkat Apa Saja</h5>
                    <p class="text-sm text-gray-500 font-medium">SAPI berbasis cloud, dapat diakses langsung melalui browser di HP, tablet, atau laptop tanpa instalasi.</p>
                </div>
                <div class="flex items-center gap-8 text-gray-400">
                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">HP</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 text-primary">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">Tablet</span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">Laptop</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 lg:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-2xl mx-auto mb-20 reveal">
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6">Investasi Masuk Akal</h3>
                <p class="text-lg text-gray-600 font-medium leading-relaxed">Pilih paket yang sesuai dengan skala bisnis Anda. Tidak ada biaya tersembunyi.</p>
            </div>

            {{--
                Kartu paket dibangun dari `plans`, bukan diketik di sini. Yang
                dipajang wajib sama dengan yang ditagihkan — halaman harga yang
                berbeda dari tagihan sungguhan adalah cacat terburuk yang bisa
                dimiliki halaman harga (`[BL-032]` butir 2, `[BL-041]`(c)).

                Paket yang disorot adalah `is_post_trial_target` — paket yang
                benar-benar dihuni tenant setelah masa gratisnya habis, bukan
                paket yang dipilih karena terlihat paling menarik.
            --}}
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto items-stretch">
                @foreach ($pricing['plans'] as $plan)
                    @php($highlighted = $plan['is_post_trial_target'])
                    <div @class([
                        'p-8 rounded-[2.5rem] flex flex-col transition-all reveal',
                        'stagger-'.min($loop->iteration, 4),
                        'bg-primary shadow-2xl shadow-primary/20 lg:-translate-y-4' => $highlighted,
                        'bg-gray-50 border border-gray-100 hover:shadow-2xl' => ! $highlighted,
                    ])>
                        @if ($highlighted)
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-brand text-white text-[10px] font-black uppercase mb-6 self-start tracking-wider">Paling Banyak Dipakai</div>
                        @endif

                        <h4 @class(['text-2xl font-black mb-2', 'text-white' => $highlighted, 'text-gray-900' => ! $highlighted])>{{ $plan['name'] }}</h4>

                        <div class="flex items-baseline gap-2 mt-6 mb-4">
                            @if ($plan['is_free'])
                                <span @class(['text-4xl font-black', 'text-white' => $highlighted, 'text-gray-900' => ! $highlighted])>Gratis</span>
                            @else
                                <span @class(['text-4xl font-black', 'text-white' => $highlighted, 'text-gray-900' => ! $highlighted])>Rp {{ number_format($plan['price'], 0, ',', '.') }}</span>
                                <span @class(['font-bold', 'text-primary/40' => $highlighted, 'text-gray-400' => ! $highlighted])>/bulan</span>
                            @endif
                        </div>

                        @if ($plan['is_free'])
                            <p @class(['text-sm font-bold mb-6', 'text-primary/40' => $highlighted, 'text-gray-500' => ! $highlighted])>
                                {{ $pricing['trial_months'] }} bulan pertama, lalu pindah ke paket berbayar.
                            </p>
                        @endif

                        {{--
                            Isi paket, dari `plans` (`[BL-067]`(a)). Istilahnya
                            mengikuti label yang sudah dipakai `/langganan`
                            — "pengguna", bukan "seat", yang tidak pernah muncul
                            di satu pun layar tenant (`[BL-067]`(c)).
                        --}}
                        <ul @class([
                            'space-y-2.5 mb-8 text-[14px] font-bold',
                            'text-white/90' => $highlighted,
                            'text-gray-600' => ! $highlighted,
                        ])>
                            <li>{{ $plan['included_seats'] }} pengguna termasuk</li>
                            <li>
                                @if ($plan['ai_daily'] === null)
                                    Analisis AI ikut bawaan platform
                                @else
                                    {{ $plan['ai_daily'] }} analisis AI/hari
                                @endif
                            </li>
                            <li>
                                @if ($plan['extra_seat_price'] <= 0)
                                    Pengguna tambahan gratis
                                @else
                                    Pengguna tambahan Rp {{ number_format($plan['extra_seat_price'], 0, ',', '.') }}/bulan
                                @endif
                            </li>
                        </ul>

                        <a href="{{ route('register') }}" @class([
                            'w-full mt-auto py-4 rounded-[1.5rem] font-black text-center transition-all',
                            'bg-white text-primary hover:bg-primary/5 shadow-xl' => $highlighted,
                            'bg-white border-2 border-gray-100 text-gray-900 hover:bg-gray-50' => ! $highlighted,
                        ])>Mulai Sekarang</a>
                    </div>
                @endforeach
            </div>

            {{--
                Kartu di atas sengaja hanya menjawab "berapa". Jalur Harga
                Adaptif, tangga bracketnya, dan syarat berpindah jalur ada di
                `/harga` — memuatnya di landing berarti menulis tabel kedua yang
                harus dijaga tetap sama (`[BL-041]`(c)).
            --}}
            <p class="text-center mt-12 reveal">
                <a href="{{ route('pricing') }}" class="font-black text-primary hover:underline">
                    Lihat rincian harga & jalur Harga Adaptif &rarr;
                </a>
            </p>
        </div>
    </section>

    {{--
        "Bagaimana harga Anda dihitung" (`[BL-066]`).

        Mekanisme inilah pembeda produk ini, dan sampai sekarang tidak satu kata
        pun tentangnya ada di permukaan publik — penjelasan yang benar hidup di
        dokumen pitching internal, bukan di halaman yang dibaca orang.

        Angkanya dari `pricing_rules` lewat `PublicPricing`, tidak diketik di
        sini (`[BL-066]`(c)). Istilah yang dipakai "Harga Adaptif", BUKAN
        "dynamic pricing": nama itu bertabrakan dengan `[BL-018]` yang memakai
        "harga dinamis" untuk diskon barang mendekati kedaluwarsa (`[BL-066]`(e)).
    --}}
    @if ($pricing['adaptive']['ladder'] !== [])
        <section id="harga-adaptif" class="py-24 lg:py-32 bg-gray-50">
            <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
                <div class="text-center max-w-2xl mx-auto mb-16 reveal">
                    <h2 class="text-primary font-black tracking-widest uppercase text-sm mb-4">Harga Adaptif</h2>
                    <h3 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6 leading-tight">Bagaimana Harga Anda Dihitung</h3>
                    <p class="text-lg text-gray-600 font-medium leading-relaxed">
                        Usaha yang omzetnya masih kecil tidak membayar seperti usaha yang sudah besar.
                        Tarifnya mengikuti, dan begini urutannya.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto mb-16">
                    <div class="reveal stagger-1">
                        <div class="w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center font-black text-lg mb-5">1</div>
                        <h4 class="text-lg font-black text-gray-900 mb-2">Omzet bulan lalu dihitung</h4>
                        <p class="text-[15px] text-gray-600 font-medium leading-relaxed">
                            Sistem menjumlahkannya sendiri dari transaksi yang tercatat di aplikasi. Anda tidak mengisi laporan apa pun.
                        </p>
                    </div>
                    <div class="reveal stagger-2">
                        <div class="w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center font-black text-lg mb-5">2</div>
                        <h4 class="text-lg font-black text-gray-900 mb-2">Angkanya jatuh ke satu kelas</h4>
                        <p class="text-[15px] text-gray-600 font-medium leading-relaxed">
                            Ada {{ count($pricing['adaptive']['ladder']) }} kelas, dan batas tiap kelas terbuka untuk dibaca — bukan penilaian yang ditentukan orang.
                        </p>
                    </div>
                    <div class="reveal stagger-3">
                        <div class="w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center font-black text-lg mb-5">3</div>
                        <h4 class="text-lg font-black text-gray-900 mb-2">Tarif bulan itu mengikuti</h4>
                        <p class="text-[15px] text-gray-600 font-medium leading-relaxed">
                            Omzet turun, tarif ikut turun bulan berikutnya. Dihitung ulang tiap bulan, bukan sekali saat mendaftar.
                        </p>
                    </div>
                </div>

                {{-- Tangganya disebut angkanya apa adanya (`[BL-066]`(a)): ia
                     memang daftar harga yang sesungguhnya, jadi menyembunyikannya
                     tidak ada gunanya. --}}
                <div class="max-w-3xl mx-auto bg-white rounded-[2.5rem] border border-gray-100 overflow-hidden reveal">
                    <div class="grid grid-cols-2 gap-px bg-gray-100">
                        <div class="bg-white px-6 py-4 text-[11px] font-black uppercase tracking-widest text-gray-400">Omzet bulan lalu</div>
                        <div class="bg-white px-6 py-4 text-[11px] font-black uppercase tracking-widest text-gray-400">Tarif bulan ini</div>
                        @foreach ($pricing['adaptive']['ladder'] as $bracket)
                            <div class="bg-white px-6 py-4 text-[15px] font-bold text-gray-600">
                                @if ($bracket['min'] === null && $bracket['max'] === null)
                                    Semua omzet
                                @elseif ($bracket['min'] === null)
                                    Di bawah Rp {{ number_format($bracket['max'], 0, ',', '.') }}
                                @elseif ($bracket['max'] === null)
                                    Rp {{ number_format($bracket['min'], 0, ',', '.') }} ke atas
                                @else
                                    Rp {{ number_format($bracket['min'], 0, ',', '.') }} – di bawah Rp {{ number_format($bracket['max'], 0, ',', '.') }}
                                @endif
                            </div>
                            <div class="bg-white px-6 py-4 text-[15px] font-black text-gray-900">Rp {{ number_format($bracket['price'], 0, ',', '.') }}</div>
                        @endforeach
                    </div>
                    @if ($pricing['adaptive']['ceiling'] !== null)
                        <div class="px-6 py-4 bg-gray-50 text-[14px] font-bold text-gray-500 border-t border-gray-100">
                            Di atas Rp {{ number_format($pricing['adaptive']['ceiling'], 0, ',', '.') }} per bulan, jalur yang berlaku adalah Harga Tetap.
                        </div>
                    @endif
                </div>

                {{--
                    Apa yang TIDAK terjadi (`[BL-066]`(b)). Tiga kalimat ini
                    menjawab keberatan yang pasti muncul lebih baik daripada satu
                    halaman fitur.

                    Butir ketiga yang diminta entrinya — "tarif yang sudah
                    dibayar terkunci (`price_locked`)" — TIDAK ditulis, karena
                    tidak benar. Penerbit tagihan tidak pernah membaca
                    `price_locked`; `issueDuePeriodInvoices()` selalu menghitung
                    ulang lewat `resolveFor()` (`SubscriptionService.php:486`),
                    dan `[BL-041]` sendiri sudah mengoreksi klaim itu pada
                    2026-08-07. Yang benar dan dipakai sebagai gantinya: tagihan
                    yang SUDAH terbit membekukan dasar perhitungannya di
                    `invoices.pricing_context`, jadi ia tidak berubah surut.
                --}}
                <div class="max-w-3xl mx-auto mt-12 grid sm:grid-cols-3 gap-6 reveal">
                    <div class="bg-white rounded-[1.75rem] border border-gray-100 p-6">
                        <p class="text-[15px] font-bold text-gray-900 mb-1.5">Isi transaksi tidak dilihat</p>
                        <p class="text-[14px] text-gray-500 font-medium leading-relaxed">
                            Yang tersimpan untuk penetapan tarif hanya dua angka per bulan: total omzet dan jumlah transaksi. Bukan barangnya, bukan pembelinya, bukan labanya.
                        </p>
                    </div>
                    <div class="bg-white rounded-[1.75rem] border border-gray-100 p-6">
                        <p class="text-[15px] font-bold text-gray-900 mb-1.5">Tidak ada laporan mandiri</p>
                        <p class="text-[14px] text-gray-500 font-medium leading-relaxed">
                            Angkanya dihitung sistem dari transaksi Anda sendiri. Tidak ada kolom yang bisa diisi terlalu rendah, dan tidak ada yang perlu Anda buktikan.
                        </p>
                    </div>
                    <div class="bg-white rounded-[1.75rem] border border-gray-100 p-6">
                        <p class="text-[15px] font-bold text-gray-900 mb-1.5">Tagihan terbit tidak berubah surut</p>
                        <p class="text-[14px] text-gray-500 font-medium leading-relaxed">
                            Dasar perhitungan setiap tagihan dibekukan saat ia terbit. Aturan tarif yang berubah kemudian hanya berlaku ke depan.
                        </p>
                    </div>
                </div>

                <p class="text-center mt-12 reveal">
                    <a href="{{ route('docs.show', ['track' => 'panduan', 'page' => 'langganan']) }}" class="font-black text-primary hover:underline">
                        Selengkapnya: panduan langganan &amp; harga &rarr;
                    </a>
                </p>
            </div>
        </section>
    @endif

    <!-- Audience Section -->
    <section class="py-24 lg:py-32 bg-gray-50 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <h2 class="text-primary font-black tracking-widest uppercase text-sm mb-4">Cocok Untuk</h2>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">Untuk Siapa <br> SAPI Dibuat</h3>
                <p class="text-gray-600 leading-relaxed font-medium mt-6">
                    Tiga bentuk usaha yang alur hariannya sudah ditangani SAPI hari ini — bukan yang direncanakan, melainkan yang sudah jalan di aplikasinya.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Audience 1 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-1">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h13v5a5 5 0 01-5 5H8a5 5 0 01-5-5V8zm13 1h2a2 2 0 010 4h-2M5 21h12" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Kafe &amp; Kedai Kopi</h4>
                    <p class="text-gray-600 leading-relaxed font-medium mb-6">
                        Pesanan yang jarang selesai sekali jalan: satu meja menambah terus sampai pulang, dan tiap gelas punya pilihannya sendiri.
                    </p>
                    <ul class="space-y-2 text-sm font-bold text-gray-500">
                        <li>Tagihan terbuka per meja, dengan umur yang dijaga</li>
                        <li>Modifier per varian — ukuran, gula, topping</li>
                        <li>Papan antrian pesanan untuk dapur</li>
                    </ul>
                </div>

                <!-- Audience 2 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-2">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Toko Kelontong &amp; Retail</h4>
                    <p class="text-gray-600 leading-relaxed font-medium mb-6">
                        Barang banyak, perputaran cepat, dan modal gampang tertimbun di rak yang salah tanpa ada yang memberi tahu.
                    </p>
                    <ul class="space-y-2 text-sm font-bold text-gray-500">
                        <li>Peringatan stok kritis, habis, dan dead stock</li>
                        <li>Saran jual yang menyebut barang dan alasannya</li>
                        <li>Katalog bervarian dengan SKU dan foto</li>
                    </ul>
                </div>

                <!-- Audience 3 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-3">
                    <div class="mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 12H4L5 9z" /></svg>
                        </div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Usaha dengan Beberapa Kasir</h4>
                    <p class="text-gray-600 leading-relaxed font-medium mb-6">
                        Begitu yang menjaga kasir bukan Anda sendiri, pertanyaannya berubah: uang di laci ini milik shift siapa, dan siapa yang mengubah apa.
                    </p>
                    <ul class="space-y-2 text-sm font-bold text-gray-500">
                        <li>Sesi kas per kasir, buka sampai tutup</li>
                        <li>Hak akses per modul untuk tiap peran</li>
                        <li>Pesan mandiri untuk pelanggan, lewat MCP</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 lg:py-32 bg-white overflow-hidden">
        <div class="max-w-4xl mx-auto px-6">
            <div class="text-center mb-20 reveal">
                <h2 class="text-primary font-black tracking-widest uppercase text-sm mb-4">FAQ</h2>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900">Pertanyaan Umum</h3>
            </div>

            <div class="space-y-4">
                <!-- FAQ 1 -->
                <div class="bg-gray-50 rounded-[2rem] border border-gray-100 overflow-hidden reveal stagger-1">
                    <button onclick="toggleFaq(1)" class="w-full px-8 py-6 flex items-center justify-between text-left hover:bg-white transition-all group">
                        <span class="text-lg font-black text-gray-900">Apakah SAPI bisa digunakan secara offline?</span>
                        <svg id="faq-icon-1" class="w-6 h-6 text-gray-400 group-hover:text-primary transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="faq-ans-1" class="hidden px-8 pb-6 text-gray-600 font-medium leading-relaxed">
                        Bisa. Saat internet putus, kasir tetap melayani memakai salinan katalog dan harga di perangkat, dan penjualannya disimpan di perangkat lalu terkirim sendiri begitu koneksi kembali. Yang memang butuh koneksi: pembayaran non-tunai dan tagihan terbuka, karena keduanya diperiksa di server.
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="bg-gray-50 rounded-[2rem] border border-gray-100 overflow-hidden reveal stagger-2">
                    <button onclick="toggleFaq(2)" class="w-full px-8 py-6 flex items-center justify-between text-left hover:bg-white transition-all group">
                        <span class="text-lg font-black text-gray-900">Bagaimana SAPI tahu stok saya bermasalah?</span>
                        <svg id="faq-icon-2" class="w-6 h-6 text-gray-400 group-hover:text-primary transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="faq-ans-2" class="hidden px-8 pb-6 text-gray-600 font-medium leading-relaxed">
                        SAPI memeriksa katalog Anda terus-menerus dan menandai empat hal: stok yang turun di bawah ambang, stok yang sudah habis, barang yang tidak terjual 30 hari terakhir, dan barang yang lewat tanggal kedaluwarsa. Aturannya sederhana dan bisa Anda periksa sendiri — bukan tebakan, dan bukan ramalan berapa hari lagi stok akan habis.
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="bg-gray-50 rounded-[2rem] border border-gray-100 overflow-hidden reveal stagger-3">
                    <button onclick="toggleFaq(3)" class="w-full px-8 py-6 flex items-center justify-between text-left hover:bg-white transition-all group">
                        <span class="text-lg font-black text-gray-900">Apakah data bisnis saya aman?</span>
                        <svg id="faq-icon-3" class="w-6 h-6 text-gray-400 group-hover:text-primary transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="faq-ans-3" class="hidden px-8 pb-6 text-gray-600 font-medium leading-relaxed">
                        Data tiap toko terpisah dan tidak pernah dibagikan ke toko lain. Untuk analisis AI, yang dikirim ke penyedia model hanya ringkasan penjualan Anda, dan hanya saat Anda meminta analisis — Anda juga bisa memakai kunci API sendiri supaya kiriman itu memakai akun Anda. Setiap pembukaan angka omzet oleh pengelola layanan tercatat di jejak audit.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Scripts -->
    <script>
    // DATA SIMULASI
    const products = [
        { id: 1, name: 'Kopi Susu', price: 22000, emoji: '☕', stock: 45 },
        { id: 2, name: 'Matcha Latte', price: 28000, emoji: '🍵', stock: 12 },
        { id: 3, name: 'Croissant', price: 18000, emoji: '🥐', stock: 8 },
        { id: 4, name: 'Es Coklat', price: 20000, emoji: '🍫', stock: 30 },
        { id: 5, name: 'Sandwich', price: 32000, emoji: '🥪', stock: 5 },
        { id: 6, name: 'Lemon Tea', price: 15000, emoji: '🍋', stock: 60 },
    ];

    {{--
        Peragaan ini dulu memajang tiga tombol aksi — "Pesan ke Supplier",
        "Promo Diskon", "Buat Bundle" — dan tak satu pun punya jalan. Dua di
        antaranya menunjuk fitur yang nol kode: pencarian `supplier` dan `bundle`
        di seluruh `app/` dan `database/migrations/` tidak mengembalikan apa pun.
        Ketiganya diturunkan jadi pil hitungan, bentuk yang memang dipakai
        `BadgeCard.vue` (`[BL-089]` butir (c)).

        Isinya ikut diluruskan ke apa yang benar-benar dihasilkan:
          - "Habis dalam 2-3 hari" adalah ramalan, sama seperti kartu hero yang
            dicabut `[BL-083]`. `BadgeHelperService` hanya membandingkan ambang.
          - Warna mengikuti `severityClasses` di `BadgeCard.vue`: stok kritis
            `warning` (amber), dead stock `info` (primary) — bukan merah/kuning.
          - Badge "Upsell" bukan keluaran Badge Helper, dan contohnya dulu
            "Bundle dengan Croissant?" padahal tidak ada bundling. Diganti
            `UpsizeVariantStrategy`, satu dari empat strategi yang sungguh ada.
    --}}
    const badgeTemplates = [
        { icon: '🟡', title: 'Stok Kritis', detail: 'Croissant — sisa 3, di bawah ambang 5.', count: '3 varian', countClass: 'bg-amber-100 text-amber-800' },
        { icon: '🔵', title: 'Dead Stock', detail: 'Lychee Soda — tidak terjual 28 hari terakhir.', count: '1 varian', countClass: 'bg-primary/10 text-primary' },
        { icon: '✨', title: 'Saran Jual', detail: 'Espresso Single — tawarkan Double.', count: 'Upsize varian', countClass: 'bg-primary/10 text-primary' },
    ];

    {{--
        Empat saran, satu per strategi yang benar-benar ada di
        `app/Services/Upsell/Strategies/`. `reason` memakai kode `UpsellEvent`
        apa adanya, dan `extra` adalah `extra_amount` pada `Suggestion`.
    --}}
    const upsellData = [
        { label: 'Espresso - Double', note: 'Naik ukuran dari Single', reason: 'price_step', extra: 7000 },
        { label: 'Tambah Extra Shot', note: 'Sering diambil bersama', reason: 'cooccurrence', extra: 5000 },
        { label: 'Lychee Soda', note: 'Belum terjual 30 hari', reason: 'dead_stock', extra: 18000 },
        { label: 'Kopi Susu Botol', note: 'Aturan owner: dorong bulan ini', reason: 'owner_rule', extra: 22000 },
    ];

    let cart = {};

    function formatRupiah(n) { return 'Rp ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

    function showTab(tab) {
        document.querySelectorAll('.demo-panel').forEach(p => p.classList.add('hidden'));
        document.querySelectorAll('.demo-tab').forEach(t => t.classList.remove('active-tab'));
        document.getElementById('panel-' + tab).classList.remove('hidden');
        document.getElementById('tab-' + tab).classList.add('active-tab');
    }

    function renderProducts() {
        const grid = document.getElementById('product-grid');
        if(!grid) return;
        grid.innerHTML = products.map(p => `
            <button onclick="addToCart(${p.id})" class="flex flex-col items-center justify-center p-5 bg-gray-50 rounded-3xl border-2 border-gray-100 hover:border-primary/40 hover:bg-primary/5 transition-all text-center gap-1">
                <span class="text-3xl">${p.emoji}</span>
                <span class="font-black text-gray-900 text-xs">${p.name}</span>
                <span class="text-xs text-gray-400 font-bold">Stok: ${p.stock}</span>
            </button>
        `).join('');
    }

    function addToCart(id) {
        const p = products.find(x => x.id === id);
        if (!p || p.stock <= (cart[id]?.qty || 0)) return;
        if (!cart[id]) cart[id] = { ...p, qty: 0 };
        cart[id].qty++;
        renderCart();
    }

    function removeFromCart(id) {
        if (cart[id]) {
            cart[id].qty--;
            if (cart[id].qty <= 0) delete cart[id];
            renderCart();
        }
    }

    function clearCart() { cart = {}; renderCart(); }

    function renderCart() {
        const container = document.getElementById('cart-items');
        const items = Object.values(cart);
        let total = 0;
        if (items.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-300 py-10 font-bold text-sm">Kosong</p>';
            document.getElementById('pos-total').textContent = 'Rp 0';
            return;
        }
        container.innerHTML = items.map(item => {
            total += item.price * item.qty;
            return `<div class="flex items-center justify-between bg-gray-50 p-4 rounded-2xl">
                <span class="text-xs font-black">${item.name} (${item.qty})</span>
                <button onclick="removeFromCart(${item.id})" class="text-red-500 font-black">X</button>
            </div>`;
        }).join('');
        document.getElementById('pos-total').textContent = formatRupiah(total);
    }

    function checkout() {
        if (Object.keys(cart).length === 0) return;
        Object.values(cart).forEach(item => {
            const p = products.find(x => x.id === item.id);
            if (p) p.stock = Math.max(0, p.stock - item.qty);
        });
        cart = {};
        renderCart();
        renderProducts();
        alert('Transaksi Berhasil!');
    }

    function renderBadges() {
        const list = document.getElementById('badge-list');
        if(!list) return;
        list.innerHTML = badgeTemplates.map(b => `
            <div class="p-6 bg-gray-50 rounded-3xl border border-gray-100 flex items-center gap-4">
                <span class="text-2xl">${b.icon}</span>
                <div class="flex-1"><p class="font-black text-gray-900 text-sm">${b.title}</p><p class="text-xs text-gray-500">${b.detail}</p></div>
                <span class="${b.countClass} px-4 py-2 rounded-xl font-black text-xs">${b.count}</span>
            </div>
        `).join('');
    }

    function renderUpsell() {
        const list = document.getElementById('upsell-list');
        if(!list) return;
        list.innerHTML = upsellData.map(u => `
            <div class="p-6 bg-gray-50 rounded-3xl border border-gray-100 flex items-center gap-5">
                <div class="flex-1">
                    <p class="font-black text-gray-900 text-sm">${u.label}</p>
                    <p class="text-xs text-gray-500">${u.note}</p>
                </div>
                <span class="bg-white border border-gray-200 text-gray-400 px-3 py-1 rounded-lg font-black text-[10px] uppercase tracking-wide">${u.reason}</span>
                <span class="font-black text-primary text-sm whitespace-nowrap">+${formatRupiah(u.extra)}</span>
            </div>
        `).join('');
    }

    function updateClock() {
        const el = document.getElementById('pos-clock');
        if (el) el.textContent = new Date().toLocaleTimeString('id-ID');
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderProducts();
        renderCart();
        renderBadges();
        renderUpsell();
        setInterval(updateClock, 1000);
        updateClock();

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => observer.observe(el));
    });

    function toggleFaq(id) {
        const ans = document.getElementById('faq-ans-' + id);
        const icon = document.getElementById('faq-icon-' + id);
        const isHidden = ans.classList.contains('hidden');
        document.querySelectorAll('[id^="faq-ans-"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="faq-icon-"]').forEach(el => el.classList.remove('rotate-180'));
        if (isHidden) { ans.classList.remove('hidden'); icon.classList.add('rotate-180'); }
    }

    function refreshBadges() { renderBadges(); }
    </script>

    <!-- Final CTA Section -->
    <section class="py-24 lg:py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="bg-primary rounded-[4rem] p-12 lg:p-20 relative overflow-hidden shadow-2xl shadow-primary/20 text-center reveal">
                <div class="absolute top-0 right-0 w-96 h-96 bg-brand rounded-full -mr-48 -mt-48 opacity-30 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-primary/90 rounded-full -ml-48 -mb-48 opacity-30 blur-3xl"></div>

                <div class="relative z-10">
                    <h3 class="text-3xl lg:text-5xl font-black text-white mb-6">Siap Membuat Bisnis Anda Lebih Pintar?</h3>
                    <p class="text-white/70 font-medium text-lg mb-10 max-w-xl mx-auto">{{ $pricing['trial_months'] }} bulan pertama gratis, lalu lanjut ke paket berbayar. Tidak perlu kartu kredit.</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" class="px-12 py-5 bg-white text-primary rounded-[1.5rem] font-black text-lg hover:bg-gray-50 transition shadow-2xl transform hover:-translate-y-1">Coba Gratis {{ $pricing['trial_months'] }} Bulan</a>
                        <a href="#demo" class="px-12 py-5 bg-white/10 text-white border-2 border-white/30 rounded-[1.5rem] font-black text-lg hover:bg-white/20 transition transform hover:-translate-y-1">Lihat Demo</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-20 bg-white border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 text-center">
            <div class="flex flex-col items-center gap-6 mb-12">
                <div class="flex items-center gap-3">
                    @include('public.partials.wordmark', ['size' => 'lg'])
                </div>
                <p class="text-gray-400 font-bold text-lg max-w-md">Smart AI POS & Inventory. Dibuat khusus untuk kemajuan UMKM Indonesia.</p>
            </div>

            <div class="flex flex-wrap justify-center gap-x-10 gap-y-4 mb-8 text-gray-500 font-black text-sm uppercase tracking-widest">
                <a href="{{ route('docs.show', ['track' => 'panduan']) }}" class="hover:text-primary transition-all">Panduan Penggunaan</a>
                <a href="{{ route('docs.show', ['track' => 'developer']) }}" class="hover:text-primary transition-all">Dokumentasi Developer</a>
                <a href="{{ route('api-docs') }}" class="hover:text-primary transition-all">Referensi API</a>
            </div>

            <div class="flex justify-center gap-10 mb-12 text-gray-400 font-black text-sm uppercase tracking-widest">
                <a href="#" class="hover:text-primary transition-all">Instagram</a>
                <a href="#" class="hover:text-primary transition-all">Twitter</a>
                <a href="#" class="hover:text-primary transition-all">LinkedIn</a>
            </div>

            <div class="text-gray-400 font-bold text-sm">
                &copy; {{ date('Y') }} SAPI. Hak Cipta Dilindungi.
            </div>
        </div>
    </footer>

</body>
</html>
