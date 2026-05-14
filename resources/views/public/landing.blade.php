<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPI - Smart AI POS & Inventory untuk UMKM</title>
    <meta name="description" content="Aplikasi Kasir (POS) cerdas dengan AI. Dilengkapi prediksi stok personal dan asisten finansial otomatis tanpa perlu bayar konsultan mahal.">
    
    <!-- Fonts: Instrument Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Fallback Tailwind CSS if Vite is not running locally -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Instrument Sans"', 'sans-serif'],
                    },
                    colors: {
                        indigo: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    },
                    borderRadius: {
                        '4xl': '2rem',
                        '5xl': '3rem',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-nav {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .hero-pill {
            background: linear-gradient(90deg, #4f46e5 0%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bg-pill {
            background: rgba(79, 70, 229, 0.1);
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
        .demo-tab:hover { border-color: #c7d2fe; color: #4f46e5; }
        .active-tab { background: #4f46e5 !important; color: white !important; border-color: #4f46e5 !important; box-shadow: 0 10px 30px rgba(79,70,229,0.2); }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans selection:bg-indigo-100 selection:text-indigo-900 antialiased overflow-x-hidden">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 glass-nav border-b border-gray-100" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="flex justify-between h-20 items-center">
                <!-- Left: Logo -->
                <div class="flex-shrink-0">
                    <a href="/" class="flex items-center gap-3 group">
                        <img src="{{ asset('sapi-logo.png') }}" alt="SAPI Logo" class="w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl shadow-xl shadow-indigo-100 group-hover:scale-105 transition-transform">
                        <span class="font-extrabold text-xl md:text-2xl tracking-tighter text-gray-900 group-hover:text-indigo-600 transition-colors">SAPI</span>
                    </a>
                </div>
                
                <!-- Right: Links & Action -->
                <div class="flex items-center gap-4 md:gap-10">
                    <div class="hidden md:flex items-center gap-8">
                        <a href="#solusi" class="text-[14px] font-bold text-gray-500 hover:text-indigo-600 transition-all">Solusi</a>
                        <a href="#fitur" class="text-[14px] font-bold text-gray-500 hover:text-indigo-600 transition-all">Fitur AI</a>
                        <a href="#demo" class="text-[14px] font-bold text-gray-500 hover:text-indigo-600 transition-all">Demo</a>
                        <a href="#pricing" class="text-[14px] font-bold text-gray-500 hover:text-indigo-600 transition-all">Harga</a>
                    </div>
                    <div class="h-6 w-px bg-gray-100 hidden md:block"></div>
                    <a href="/login" class="hidden sm:block bg-indigo-600 text-white px-8 py-3 rounded-full text-[14px] font-black hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100 hover:shadow-indigo-200 hover:-translate-y-0.5">
                        Login
                    </a>
                    
                    <!-- Hamburger Button -->
                    <button @click="open = !open" class="md:hidden p-2 text-gray-600 hover:text-indigo-600 transition-colors">
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
            <a @click="open = false" href="#solusi" class="block text-lg font-black text-gray-900 hover:text-indigo-600">Solusi</a>
            <a @click="open = false" href="#fitur" class="block text-lg font-black text-gray-900 hover:text-indigo-600">Fitur AI</a>
            <a @click="open = false" href="#demo" class="block text-lg font-black text-gray-900 hover:text-indigo-600">Demo</a>
            <a @click="open = false" href="#pricing" class="block text-lg font-black text-gray-900 hover:text-indigo-600">Harga</a>
            <div class="pt-4 border-t border-gray-100">
                <a @click="open = false" href="/login" class="block w-full bg-indigo-600 text-white text-center py-4 rounded-2xl font-black shadow-xl shadow-indigo-100">Login / Daftar Gratis</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-36 pb-24 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="grid lg:grid-cols-12 gap-16 items-center">
                <div class="lg:col-span-6 reveal-left">

                    <h1 class="text-4xl sm:text-5xl lg:text-7xl font-black tracking-tight text-gray-900 mb-8 leading-[1.2] lg:leading-[1.1]">
                        Kelola Toko Jadi <br class="hidden sm:block">
                        <span class="bg-pill text-indigo-600">Lebih Pintar</span> <br class="hidden sm:block">
                        Dengan AI.
                    </h1>
                    <p class="text-lg sm:text-xl text-gray-600 mb-10 leading-relaxed max-w-lg font-medium">
                        SAPI bukan sekadar kasir. Kami memprediksi stok Anda, menganalisis pola penjualan, dan memberi saran aksi nyata—otomatis.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 sm:gap-5">
                        <a href="/login" class="px-8 sm:px-10 py-4 sm:py-5 bg-indigo-600 text-white rounded-[1.2rem] sm:rounded-[1.5rem] font-extrabold text-base sm:text-lg hover:bg-indigo-700 shadow-2xl shadow-indigo-200 transition-all transform hover:-translate-y-1 text-center">
                            Daftar Gratis
                        </a>
                        <a href="#fitur" class="px-8 sm:px-10 py-4 sm:py-5 bg-white text-gray-900 border-2 border-gray-100 rounded-[1.2rem] sm:rounded-[1.5rem] font-extrabold text-base sm:text-lg hover:border-gray-200 transition-all text-center">
                            Lihat Fitur
                        </a>
                    </div>
                </div>
                <div class="lg:col-span-6 relative reveal-right">
                    <!-- Main Mockup -->
                    <div class="relative z-10 bg-gray-50 rounded-[3rem] p-4 border border-gray-100 shadow-2xl">
                        <img src="{{ asset('Dashboard-owner.png') }}" alt="Mockup SAPI" class="rounded-[2.5rem] w-full">
                    </div>
                    
                    <!-- Floating Elements -->
                    <div class="absolute -top-10 -right-5 z-20 float-animation hidden sm:block">
                        <div class="bg-white p-5 rounded-3xl shadow-2xl border border-gray-100 flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase">Prediksi Stok</p>
                                <p class="text-sm font-black text-gray-900">Aman Hingga 14 Hari</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="absolute bottom-10 -left-10 z-20 float-animation-delay hidden sm:block">
                        <div class="bg-white p-6 rounded-3xl shadow-2xl border border-gray-100">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-3 h-3 bg-indigo-600 rounded-full"></div>
                                <p class="text-sm font-black text-gray-900">Badge Helper</p>
                            </div>
                            <p class="text-sm text-gray-600 font-medium leading-tight">
                                "Kopi Susu Gula Aren mulai <br> sepi, beri diskon 15%?"
                            </p>
                            <button class="mt-4 w-full py-2 bg-indigo-50 text-indigo-600 rounded-xl text-xs font-black">Eksekusi Sekarang</button>
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
                <h2 class="text-indigo-600 font-black tracking-widest uppercase text-sm mb-4">Masalah Diam-Diam</h2>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">
                    Kenapa Mengelola Toko <br> Masih Terasa Berat?
                </h3>
            </div>
            
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Problem 1 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-1">
                    <div class="flex justify-between items-start mb-8">
                        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center font-black text-2xl">01</div>
                        <div class="text-gray-200 font-black text-6xl">?</div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Menebak-nebak Stok</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Terlalu banyak stok yang tidak laku (Dead Stock) atau kehabisan barang saat sedang ramai. Modal tertimbun di tempat yang salah.
                    </p>
                </div>
                
                <!-- Problem 2 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-2">
                    <div class="flex justify-between items-start mb-8">
                        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center font-black text-2xl">02</div>
                        <div class="text-gray-200 font-black text-6xl">?</div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Grafik Kosong Tanpa Arti</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Aplikasi kasir biasa cuma kasih grafik. Tapi apa artinya? Anda tetap bingung langkah apa yang harus diambil hari ini.
                    </p>
                </div>
                
                <!-- Problem 3 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-3">
                    <div class="flex justify-between items-start mb-8">
                        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center font-black text-2xl">03</div>
                        <div class="text-gray-200 font-black text-6xl">?</div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Data Tersebar</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Rekapan manual yang sering salah hitung. Antara stok di gudang dan laporan penjualan sering tidak nyambung.
                    </p>
                </div>
                
                <!-- Problem 4 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 group hover:shadow-2xl transition-all reveal stagger-4">
                    <div class="flex justify-between items-start mb-8">
                        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center font-black text-2xl">04</div>
                        <div class="text-gray-200 font-black text-6xl">?</div>
                    </div>
                    <h4 class="text-2xl font-black text-gray-900 mb-4">Biaya Konsultan Mahal</h4>
                    <p class="text-gray-600 leading-relaxed font-medium">
                        Ingin bisnis profesional tapi tidak mampu bayar konsultan finansial. Anda akhirnya jalan di tempat tanpa arah yang jelas.
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
                <div class="bg-indigo-600 p-12 rounded-[3rem] text-white shadow-2xl shadow-indigo-200 reveal-right">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500 text-white text-xs font-black uppercase mb-8">
                        Cara SAPI
                    </div>
                    <h4 class="text-3xl font-black mb-10 text-white">Otomatis & Pintar</h4>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-indigo-400 text-white rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <p class="text-lg font-bold">"Stok Anda aman untuk 10 hari ke depan."</p>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-indigo-400 text-white rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <p class="text-lg font-bold">"Laba bersih Anda naik 20% bulan ini."</p>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-indigo-400 text-white rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <p class="text-lg font-bold">"Promo bundling A & B efektif tingkatkan penjualan."</p>
                        </li>
                    </ul>
                    <div class="mt-12 text-center md:text-left">
                        <a href="/login" class="inline-block px-8 py-4 bg-white text-indigo-600 rounded-2xl font-black hover:bg-indigo-50 transition-all">Ganti ke SAPI Sekarang</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tabbed Features Section -->
    <section id="fitur" class="py-24 lg:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">Segala yang Anda Butuhkan untuk <span class="text-indigo-600">Scale-up</span></h3>
            </div>
            
            <div class="bg-gray-50 rounded-[4rem] p-10 lg:p-16 border border-gray-100 reveal">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div class="order-2 lg:order-1">
                        <div class="space-y-8">
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm reveal stagger-1">
                                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Core POS System</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Transaksi super cepat dengan dukungan multi-pembayaran (QRIS, Tunai, Transfer). Manajemen produk dan varian yang fleksibel untuk segala jenis bisnis UMKM.
                                </p>
                            </div>
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm reveal stagger-2">
                                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Smart Layer AI</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Bukan sekadar data. AI kami mempelajari pola unik di toko Anda dan memberikan saran aksi nyata melalui Badge Helper yang intuitif.
                                </p>
                            </div>
                            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm reveal stagger-3">
                                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mb-6">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                </div>
                                <h4 class="text-2xl font-black text-gray-900 mb-4">Inventory Prediction</h4>
                                <p class="text-lg text-gray-600 font-medium leading-relaxed">
                                    Hindari Dead Stock. SAPI memprediksi kebutuhan stok Anda berdasarkan tren historis, memastikan Anda selalu punya barang saat pelanggan mencari.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="relative order-1 lg:order-2 reveal stagger-2">
                        <div class="sticky top-32">
                            <div class="relative">
                                <div class="absolute -inset-4 bg-indigo-100 rounded-[3rem] rotate-3 opacity-50"></div>
                                <div class="relative bg-white rounded-[2.5rem] p-4 shadow-2xl border border-gray-100 transform -rotate-2 transition-transform hover:rotate-0 duration-500">
                                    <img src="{{ asset('POS-Interface.png') }}" alt="Fitur SAPI" class="rounded-[2rem] w-full shadow-inner">
                                    <div class="absolute -bottom-6 -right-6 bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-xl">
                                        Interface Kasir
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
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900">Bukti Nyata Sistem SAPI</h3>
                <p class="text-lg text-gray-600 font-medium mt-4">Kami tidak hanya bicara fitur, inilah tampilan asli dashboard dan manajemen SAPI yang digunakan ribuan UMKM.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Report -->
                <div class="group reveal stagger-1">
                    <div class="bg-white rounded-[2.5rem] p-4 shadow-lg border border-gray-100 transition-all group-hover:-translate-y-2 group-hover:shadow-2xl">
                        <div class="relative rounded-[1.5rem] overflow-hidden mb-6 aspect-video">
                            <img src="{{ asset('Reports-Daily.png') }}" alt="Laporan Harian" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-indigo-900/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <h4 class="text-xl font-black text-gray-900 px-2">Laporan Harian Pintar</h4>
                        <p class="text-sm text-gray-500 font-bold px-2 mt-2">Analisis penjualan real-time tanpa ribet.</p>
                    </div>
                </div>

                <!-- Stock -->
                <div class="group reveal stagger-2">
                    <div class="bg-white rounded-[2.5rem] p-4 shadow-lg border border-gray-100 transition-all group-hover:-translate-y-2 group-hover:shadow-2xl">
                        <div class="relative rounded-[1.5rem] overflow-hidden mb-6 aspect-video">
                            <img src="{{ asset('Stock-Management.png') }}" alt="Manajemen Stok" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-indigo-900/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <h4 class="text-xl font-black text-gray-900 px-2">Kontrol Inventori Akurat</h4>
                        <p class="text-sm text-gray-500 font-bold px-2 mt-2">Pantau pergerakan barang setiap detik.</p>
                    </div>
                </div>

                <!-- Product -->
                <div class="group reveal stagger-3">
                    <div class="bg-white rounded-[2.5rem] p-4 shadow-lg border border-gray-100 transition-all group-hover:-translate-y-2 group-hover:shadow-2xl">
                        <div class="relative rounded-[1.5rem] overflow-hidden mb-6 aspect-video">
                            <img src="{{ asset('Product-List.png') }}" alt="Daftar Produk" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-indigo-900/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <h4 class="text-xl font-black text-gray-900 px-2">Katalog Produk Modern</h4>
                        <p class="text-sm text-gray-500 font-bold px-2 mt-2">Kelola ribuan SKU dengan sangat mudah.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Onboarding Flow Section -->
    <section class="py-24 lg:py-32 bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-600 rounded-full text-xs font-black uppercase tracking-widest mb-6">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg>
                    AI-Powered Setup
                </div>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">Mulai Berjualan Lebih Cerdas Hanya dalam <span class="text-indigo-600">3 Menit</span></h3>
                <p class="text-lg text-gray-600 font-medium mt-6">Lupakan input manual yang melelahkan. Biarkan AI kami yang menyiapkan segalanya untuk Anda.</p>
            </div>

            <div class="relative">
                <!-- Vertical Line -->
                <div class="absolute left-1/2 top-0 bottom-0 w-1 bg-gray-100 -translate-x-1/2 hidden lg:block"></div>

                <div class="space-y-24 lg:space-y-0">
                    <!-- Step 1 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal">
                        <div class="lg:text-right lg:pr-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-indigo-200">1</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Daftar / Login Cepat</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md lg:ml-auto">Cukup hubungkan akun Google Anda atau daftar dengan email. Tanpa formulir panjang yang membosankan.</p>
                        </div>
                        <div class="lg:pl-24 bg-gray-50 rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-inner group transition-all hover:bg-indigo-50">
                            <div class="bg-white p-6 rounded-2xl shadow-xl border border-gray-100 flex flex-col items-center gap-4">
                                <div class="w-full h-12 border border-gray-100 rounded-xl flex items-center justify-center gap-3 font-bold text-gray-600 text-sm">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                    Lanjutkan dengan Google
                                </div>
                                <div class="w-full flex items-center gap-2 px-10">
                                    <div class="flex-1 h-px bg-gray-100"></div>
                                    <span class="text-[10px] font-bold text-gray-300 uppercase">Atau</span>
                                    <div class="flex-1 h-px bg-gray-100"></div>
                                </div>
                                <div class="w-full space-y-2">
                                    <div class="h-10 bg-gray-50 rounded-lg w-full"></div>
                                    <div class="h-10 bg-indigo-600 rounded-lg w-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal pt-24 lg:pt-32">
                        <div class="lg:order-2 lg:pl-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-indigo-200">2</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Setup AI Bisnis Anda</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md">Cukup masukkan nama toko dan pilih kategori usaha Anda. AI SAPI akan langsung mengenali kebutuhan bisnis Anda.</p>
                        </div>
                        <div class="lg:order-1 lg:pr-24 bg-gray-50 rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-inner group transition-all hover:bg-indigo-50">
                            <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100 space-y-4">
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase mb-2 block">Nama Toko</label>
                                    <div class="h-12 border-2 border-indigo-100 rounded-xl flex items-center px-4 font-bold text-gray-700">Kopi Nusantara</div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase mb-2 block">Kategori</label>
                                    <div class="h-12 border-2 border-indigo-100 rounded-xl flex items-center px-4 font-bold text-gray-700 justify-between">
                                        Coffee Shop
                                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal pt-24 lg:pt-32">
                        <div class="lg:text-right lg:pr-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-indigo-200">3</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">AI Menyiapkan Toko Anda</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md lg:ml-auto">Berdasarkan kategori, SAPI otomatis membuatkan kategori produk, daftar menu populer, dan saran stok awal.</p>
                        </div>
                        <div class="lg:pl-24 bg-gray-50 rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-inner group transition-all hover:bg-indigo-50">
                            <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100 flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-4 animate-pulse">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                </div>
                                <h5 class="font-black text-gray-900 mb-1 text-sm">Menyiapkan Toko...</h5>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Membuat Katalog Produk Kopi</p>
                                <div class="w-full bg-gray-100 h-1.5 rounded-full mt-6 overflow-hidden">
                                    <div class="bg-indigo-600 h-full w-2/3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative grid lg:grid-cols-2 gap-12 items-center reveal pt-24 lg:pt-32">
                        <div class="lg:order-2 lg:pl-24">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-2xl font-black text-xl mb-6 lg:absolute lg:left-1/2 lg:-translate-x-1/2 lg:z-10 shadow-xl shadow-indigo-200">4</div>
                            <h4 class="text-2xl font-black text-gray-900 mb-4">Siap Digunakan!</h4>
                            <p class="text-gray-600 font-medium leading-relaxed max-w-md">Hanya dalam hitungan detik, dashboard dan sistem kasir Anda sudah siap. Langsung mulai transaksi pertama Anda.</p>
                        </div>
                        <div class="lg:order-1 lg:pr-24 bg-gray-50 rounded-[3rem] p-6 sm:p-8 border border-gray-100 shadow-inner group transition-all hover:bg-indigo-50">
                            <div class="bg-white p-3 rounded-2xl shadow-xl border border-gray-100 overflow-hidden relative">
                                <img src="{{ asset('POS-Interface.png') }}" class="w-full opacity-30 blur-[2px] rounded-lg">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="bg-white px-8 py-4 rounded-2xl shadow-2xl border border-indigo-100 flex items-center gap-4 animate-bounce">
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
            <div class="mt-16 bg-gray-50 rounded-3xl p-8 border border-gray-100 flex flex-col md:flex-row items-center justify-between gap-8 reveal">
                <div class="text-center md:text-left">
                    <h5 class="text-lg font-black text-gray-900 mb-1">Akses dari Perangkat Apa Saja</h5>
                    <p class="text-sm text-gray-500 font-medium">SAPI berbasis cloud, dapat diakses langsung melalui browser di HP, tablet, atau laptop tanpa instalasi.</p>
                </div>
                <div class="flex items-center gap-8 text-gray-400">
                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">HP</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 text-indigo-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">Tablet</span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">Laptop</span>
                    </div>
                </div>
            </div>
            
            <!-- Final CTA Card -->
            <div class="mt-32 text-center reveal">
                <div class="bg-indigo-600 rounded-[4rem] p-12 lg:p-20 relative overflow-hidden shadow-2xl shadow-indigo-200">
                    <div class="absolute top-0 right-0 w-96 h-96 bg-indigo-500 rounded-full -mr-48 -mt-48 opacity-30 blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-96 h-96 bg-indigo-700 rounded-full -ml-48 -mb-48 opacity-30 blur-3xl"></div>
                    
                    <div class="relative z-10">
                        <h3 class="text-3xl lg:text-5xl font-black text-white mb-8">Siap Membuat Bisnis Anda Lebih Pintar?</h3>
                        <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                            <a href="/login" class="px-12 py-6 bg-white text-indigo-600 rounded-[1.5rem] font-black text-xl hover:bg-gray-50 transition shadow-2xl transform hover:-translate-y-1">Daftar Sekarang</a>
                            <div class="flex items-center gap-4">
                                <div class="flex -space-x-3">
                                    <img src="{{ asset('avatar_andi.png') }}" class="w-10 h-10 rounded-full border-2 border-indigo-600 object-cover">
                                    <img src="{{ asset('avatar_santi.png') }}" class="w-10 h-10 rounded-full border-2 border-indigo-600 object-cover">
                                    <img src="{{ asset('avatar_budi.png') }}" class="w-10 h-10 rounded-full border-2 border-indigo-600 object-cover">
                                </div>
                                <span class="text-indigo-100 font-bold text-sm">Bergabung dengan 2.000+ UMKM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DEMO SIMULATION SECTION (Moved Below Features) -->
    <section id="demo" class="py-24 lg:py-32 bg-gray-50 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-600 text-sm font-bold mb-6">
                    <span class="w-2 h-2 bg-indigo-600 rounded-full animate-pulse"></span>
                    Coba Langsung — Tanpa Daftar
                </div>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900">Rasakan Cara Kerja SAPI</h3>
                <p class="text-lg text-gray-600 font-medium mt-4">Simulasi interaktif tiga fitur utama. Klik, tambah produk, dan lihat AI bekerja.</p>
            </div>

            <!-- Tab Navigation -->
            <div class="flex flex-wrap justify-center gap-4 mb-10 reveal stagger-1">
                <button onclick="showTab('pos')" id="tab-pos" class="demo-tab active-tab px-8 py-3 rounded-2xl font-black text-base transition-all">🧾 Simulasi Kasir</button>
                <button onclick="showTab('badge')" id="tab-badge" class="demo-tab px-8 py-3 rounded-2xl font-black text-base transition-all">🤖 Badge Helper AI</button>
                <button onclick="showTab('predict')" id="tab-predict" class="demo-tab px-8 py-3 rounded-2xl font-black text-base transition-all">📈 Prediksi Stok</button>
            </div>

            <!-- TAB 1: POS KASIR -->
            <div id="panel-pos" class="demo-panel reveal stagger-2">
                <div class="bg-white rounded-[3rem] shadow-xl border border-gray-100 overflow-hidden">
                    <div class="bg-indigo-600 px-8 py-5 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center text-white font-black">S</div>
                            <span class="text-white font-black text-lg">SAPI — Mode Kasir</span>
                        </div>
                        <div class="text-indigo-200 font-bold text-sm" id="pos-clock"></div>
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
                                    <span id="pos-total" class="font-black text-indigo-600 text-2xl">Rp 0</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <button onclick="clearCart()" class="py-4 bg-gray-100 text-gray-700 rounded-2xl font-black hover:bg-gray-200 transition-all">Batal</button>
                                    <button onclick="checkout()" class="py-4 bg-indigo-600 text-white rounded-2xl font-black hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">Bayar</button>
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
                        <span class="text-white font-black text-lg">🤖 SAPI Badge Helper AI</span>
                    </div>
                    <div class="p-8 lg:p-12">
                        <div class="space-y-5" id="badge-list"></div>
                        <div class="mt-10 text-center">
                            <button onclick="refreshBadges()" class="px-8 py-4 bg-gray-900 text-white rounded-2xl font-black hover:bg-gray-800 transition-all">🔄 Refresh Analisis AI</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PREDIKSI STOK -->
            <div id="panel-predict" class="demo-panel hidden">
                <div class="bg-white rounded-[3rem] shadow-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-900 px-8 py-5 flex items-center justify-between">
                        <span class="text-white font-black text-lg">📈 Prediksi Stok (Machine Learning)</span>
                    </div>
                    <div class="p-8 lg:p-12">
                        <div class="grid md:grid-cols-3 gap-6 mb-10" id="predict-cards"></div>
                        <div class="mt-8 flex items-center justify-center">
                            <button onclick="runPrediction()" class="px-10 py-4 bg-indigo-600 text-white rounded-2xl font-black hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-200 flex items-center gap-3">
                                <svg class="w-5 h-5 animate-spin hidden" id="predict-spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Jalankan Ulang Prediksi AI
                            </button>
                        </div>
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
            
            <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                <!-- Basic -->
                <div class="bg-gray-50 p-12 rounded-[3.5rem] border border-gray-100 flex flex-col hover:shadow-2xl transition-all reveal stagger-1">
                    <h4 class="text-2xl font-black text-gray-900 mb-2">Core POS</h4>
                    <p class="text-gray-500 font-bold mb-8 italic">Untuk operasional harian.</p>
                    <div class="flex items-baseline gap-2 mb-10">
                        <span class="text-5xl font-black text-gray-900">Rp 149k</span>
                        <span class="text-gray-400 font-bold">/bulan</span>
                    </div>
                    <ul class="space-y-5 mb-12 flex-1 text-[15px] font-bold text-gray-600">
                        <li class="flex items-center gap-3">
                            <div class="w-5 h-5 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            Transaksi Kasir & Multi-payment
                        </li>
                        <li class="flex items-center gap-3">
                            <div class="w-5 h-5 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            Manajemen Produk & Varian
                        </li>
                    </ul>
                    <a href="/login" class="w-full py-5 bg-white border-2 border-gray-100 text-gray-900 rounded-[1.5rem] font-black text-center hover:bg-gray-50 transition-all">Pilih Paket</a>
                </div>
                
                <!-- Pro -->
                <div class="bg-indigo-600 p-12 rounded-[3.5rem] flex flex-col shadow-2xl shadow-indigo-200 transform md:-translate-y-4 reveal stagger-2">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500 text-white text-[10px] font-black uppercase mb-6 self-start tracking-wider">Paling Populer</div>
                    <h4 class="text-2xl font-black text-white mb-2">Smart SAPI</h4>
                    <p class="text-indigo-200 font-bold mb-8 italic">Asisten AI Aktif.</p>
                    <div class="flex items-baseline gap-2 mb-10">
                        <span class="text-5xl font-black text-white">Rp 299k</span>
                        <span class="text-indigo-200 font-bold">/bulan</span>
                    </div>
                    <ul class="space-y-5 mb-12 flex-1 text-[15px] font-bold text-white">
                        <li class="flex items-center gap-3">
                            <div class="w-5 h-5 bg-white text-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            Semua Fitur Core POS
                        </li>
                        <li class="flex items-center gap-3">
                            <div class="w-5 h-5 bg-white text-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            AI Prediksi Stok & Badge Helper
                        </li>
                    </ul>
                    <a href="/login" class="w-full py-5 bg-white text-indigo-600 rounded-[1.5rem] font-black text-center hover:bg-indigo-50 transition-all shadow-xl">Mulai Sekarang</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-24 lg:py-32 bg-gray-50 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <h2 class="text-indigo-600 font-black tracking-widest uppercase text-sm mb-4">Testimoni</h2>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight">Cerita Sukses <br> Bersama SAPI</h3>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 reveal stagger-1">
                    <p class="text-xl text-gray-900 font-bold leading-relaxed mb-8">
                        "SAPI sangat membantu café saya. Dulu sering kehabisan biji kopi di jam sibuk, sekarang AI-nya selalu kasih tau 2 hari sebelumnya."
                    </p>
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('avatar_andi.png') }}" alt="Andi" class="w-12 h-12 rounded-full border-2 border-white shadow-md object-cover">
                        <div>
                            <p class="font-black text-gray-900">Andi</p>
                            <p class="text-sm font-bold text-gray-400">Owner Senja Coffee</p>
                        </div>
                    </div>
                </div>
                
                <!-- Testimonial 2 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 reveal stagger-2">
                    <p class="text-xl text-gray-900 font-bold leading-relaxed mb-8">
                        "Badge Helper-nya beneran ajaib. Saya nggak perlu lagi pusing liat grafik rumit, tinggal eksekusi saran dari SAPI."
                    </p>
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('avatar_santi.png') }}" alt="Santi" class="w-12 h-12 rounded-full border-2 border-white shadow-md object-cover">
                        <div>
                            <p class="font-black text-gray-900">Santi</p>
                            <p class="text-sm font-bold text-gray-400">Manajer Roti Enak</p>
                        </div>
                    </div>
                </div>
                
                <!-- Testimonial 3 -->
                <div class="bg-white p-10 rounded-[2.5rem] border border-gray-100 reveal stagger-3">
                    <p class="text-xl text-gray-900 font-bold leading-relaxed mb-8">
                        "Fitur prediksi stoknya akurat banget. Modal saya jadi nggak tertimbun di barang yang nggak laku."
                    </p>
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('avatar_budi.png') }}" alt="Budi" class="w-12 h-12 rounded-full border-2 border-white shadow-md object-cover">
                        <div>
                            <p class="font-black text-gray-900">Budi</p>
                            <p class="text-sm font-bold text-gray-400">Toko Kelontong Modern</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 lg:py-32 bg-white overflow-hidden">
        <div class="max-w-4xl mx-auto px-6">
            <div class="text-center mb-20 reveal">
                <h2 class="text-indigo-600 font-black tracking-widest uppercase text-sm mb-4">FAQ</h2>
                <h3 class="text-4xl lg:text-5xl font-black text-gray-900">Pertanyaan Umum</h3>
            </div>
            
            <div class="space-y-4">
                <!-- FAQ 1 -->
                <div class="bg-gray-50 rounded-[2rem] border border-gray-100 overflow-hidden reveal stagger-1">
                    <button onclick="toggleFaq(1)" class="w-full px-8 py-6 flex items-center justify-between text-left hover:bg-white transition-all group">
                        <span class="text-lg font-black text-gray-900">Apakah SAPI bisa digunakan secara offline?</span>
                        <svg id="faq-icon-1" class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="faq-ans-1" class="hidden px-8 pb-6 text-gray-600 font-medium leading-relaxed">
                        SAPI berbasis cloud untuk memastikan sinkronisasi AI yang akurat, namun kami memiliki mode cache terbatas untuk transaksi kasir saat internet tidak stabil.
                    </div>
                </div>
                
                <!-- FAQ 2 -->
                <div class="bg-gray-50 rounded-[2rem] border border-gray-100 overflow-hidden reveal stagger-2">
                    <button onclick="toggleFaq(2)" class="w-full px-8 py-6 flex items-center justify-between text-left hover:bg-white transition-all group">
                        <span class="text-lg font-black text-gray-900">Bagaimana cara AI memprediksi stok saya?</span>
                        <svg id="faq-icon-2" class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="faq-ans-2" class="hidden px-8 pb-6 text-gray-600 font-medium leading-relaxed">
                        AI kami menganalisis data transaksi historis toko Anda selama 90 hari terakhir untuk menemukan pola musiman dan tren harian unik toko Anda.
                    </div>
                </div>
                
                <!-- FAQ 3 -->
                <div class="bg-gray-50 rounded-[2rem] border border-gray-100 overflow-hidden reveal stagger-3">
                    <button onclick="toggleFaq(3)" class="w-full px-8 py-6 flex items-center justify-between text-left hover:bg-white transition-all group">
                        <span class="text-lg font-black text-gray-900">Apakah data bisnis saya aman?</span>
                        <svg id="faq-icon-3" class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="faq-ans-3" class="hidden px-8 pb-6 text-gray-600 font-medium leading-relaxed">
                        Sangat aman. Kami menggunakan enkripsi standar industri dan data Anda tidak akan pernah dibagikan ke tenant lain. AI dilatih khusus per-toko.
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

    const badgeTemplates = [
        { type: 'danger', icon: '🔴', title: 'Stok Kritis', product: 'Croissant', detail: 'Sisa 8 pcs. Habis dalam 2-3 hari.', action: 'Pesan ke Supplier', actionClass: 'bg-red-100 text-red-700' },
        { type: 'warning', icon: '🟡', title: 'Dead Stock', product: 'Lychee Soda', detail: 'Tidak laku 28 hari.', action: 'Promo Diskon', actionClass: 'bg-yellow-100 text-yellow-700' },
        { type: 'info', icon: '🔵', title: 'Upsell', product: 'Kopi Susu', detail: 'Bundle dengan Croissant?', action: 'Buat Bundle', actionClass: 'bg-blue-100 text-blue-700' },
    ];

    const predictData = [
        { name: 'Kopi Susu', emoji: '☕', current: 45, predicted: 7, days: 6, status: 'warning' },
        { name: 'Matcha Latte', emoji: '🍵', current: 12, predicted: 4, days: 3, status: 'danger' },
        { name: 'Croissant', emoji: '🥐', current: 8, predicted: 3, days: 2, status: 'danger' },
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
            <button onclick="addToCart(${p.id})" class="flex flex-col items-center justify-center p-5 bg-gray-50 rounded-3xl border-2 border-gray-100 hover:border-indigo-300 hover:bg-indigo-50 transition-all text-center gap-1">
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
                <button class="${b.actionClass} px-4 py-2 rounded-xl font-black text-xs">${b.action}</button>
            </div>
        `).join('');
    }

    function renderPrediction() {
        const grid = document.getElementById('predict-cards');
        if(!grid) return;
        grid.innerHTML = predictData.map(d => `
            <div class="p-6 rounded-3xl border-2 bg-white flex flex-col gap-3">
                <span class="text-2xl">${d.emoji}</span>
                <p class="font-black text-gray-900 text-sm">${d.name}</p>
                <div class="bg-gray-50 p-3 rounded-xl">
                    <p class="text-[10px] font-black text-gray-400 uppercase">Estimasi Habis</p>
                    <p class="text-sm font-black text-gray-900">${d.days} Hari</p>
                </div>
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
        renderPrediction();
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
    </script>

    <!-- Footer -->
    <footer class="py-20 bg-white border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 text-center">
            <div class="flex flex-col items-center gap-6 mb-12">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('sapi-logo.png') }}" alt="SAPI Logo" class="w-12 h-12 rounded-2xl grayscale hover:grayscale-0 transition-all duration-500">
                    <span class="font-extrabold text-2xl tracking-tighter text-gray-900">SAPI</span>
                </div>
                <p class="text-gray-400 font-bold text-lg max-w-md">Smart AI POS & Inventory. Dibuat khusus untuk kemajuan UMKM Indonesia.</p>
            </div>
            
            <div class="flex justify-center gap-10 mb-12 text-gray-500 font-black text-sm uppercase tracking-widest">
                <a href="#" class="hover:text-indigo-600 transition-all">Instagram</a>
                <a href="#" class="hover:text-indigo-600 transition-all">Twitter</a>
                <a href="#" class="hover:text-indigo-600 transition-all">LinkedIn</a>
            </div>
            
            <div class="text-gray-400 font-bold text-sm">
                &copy; {{ date('Y') }} SAPI. Hak Cipta Dilindungi.
            </div>
        </div>
    </footer>

</body>
</html>
