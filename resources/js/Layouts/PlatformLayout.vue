<script setup>
import { usePage, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import LogoutConfirmDialog from '@/Components/LogoutConfirmDialog.vue';
import { useLogoutConfirm } from '@/composables/useLogoutConfirm';
import { computed, ref, h, defineComponent, onMounted, onBeforeUnmount } from 'vue';

const page = usePage();

// Panel ini memakai auth.platformUser, BUKAN auth.user. Keduanya sengaja
// dipisah di HandleInertiaRequests: akun platform tidak punya role maupun
// tenant_id, jadi menumpangkannya di auth.user akan mengirim null diam-diam ke
// komponen yang mengharapkan data tenant.
const platformUser = computed(() => page.props.auth.platformUser);
const modules = computed(() => platformUser.value?.modules ?? []);

// Sama persis dengan OwnerLayout: terbuka di desktop, tertutup di ponsel.
// Sebelumnya panel ini selalu mulai terbuka, sehingga di ponsel pengguna selalu
// disambut sidebar melayang yang harus ditutup dulu.
const isDesktop = typeof window !== 'undefined' && window.innerWidth >= 1024;
const sidebarOpen = ref(isDesktop);
const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value; };

const handleNavClick = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
        sidebarOpen.value = false;
    }
};

const collapsedGroups = ref({});
const toggleGroup = (label) => {
    collapsedGroups.value[label] = !collapsedGroups.value[label];
};

// ── Ikon ───────────────────────────────────────────────────────────────────
// Ditulis dengan cara yang sama seperti OwnerLayout — satu peta path, satu
// komponen perender. Dua panel yang menggambar ikonnya dengan cara berbeda
// akan pelan-pelan punya ukuran dan ketebalan garis yang berbeda pula.
const iconPaths = {
    home: [
        'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z',
        'M9 22V12h6v10',
    ],
    'office-building': [
        'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5',
        'M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    ],
    'credit-card':
        'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    tag: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 10V5a2 2 0 012-2z',
    clipboard:
        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    users:
        'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    sparkles:
        'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    shield:
        'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    logout: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    chevron: 'M19 9l-7 7-7-7',
};

const NavIcon = defineComponent({
    props: { name: { type: String, required: true } },
    setup(props) {
        return () => {
            const raw = iconPaths[props.name] ?? iconPaths.home;
            const paths = Array.isArray(raw) ? raw : [raw];

            return h(
                'svg',
                {
                    class: 'w-4 h-4 flex-shrink-0',
                    fill: 'none',
                    stroke: 'currentColor',
                    viewBox: '0 0 24 24',
                    'aria-hidden': 'true',
                },
                paths.map((d) =>
                    h('path', {
                        'stroke-linecap': 'round',
                        'stroke-linejoin': 'round',
                        'stroke-width': '2',
                        d,
                    }),
                ),
            );
        };
    },
});

// ── Navigasi ───────────────────────────────────────────────────────────────
// Difilter dari daftar modul yang sama dengan gerbang route
// (config/platform-rbac.php) — satu sumber kebenaran, bukan dua daftar.
// `modules` berarti "salah satu cukup", mengikuti gerbang `platform.can` yang
// juga menerima beberapa modul. `ownerOnly` untuk hal yang dijaga penanda
// is_owner, bukan modul grantable.
const sidebarGroups = [
    {
        label: null,
        items: [
            { name: 'Beranda', href: '/platform', icon: 'home' },
        ],
    },
    {
        label: 'Klien',
        items: [
            { name: 'Daftar Tenant', href: '/platform/tenants', icon: 'office-building', modules: ['tenants'] },
        ],
    },
    {
        label: 'Komersial',
        items: [
            // Langganan dan tagihan adalah satu urusan yang sama dilihat dari
            // dua sisi. Dulu dua menu terpisah, dan akibatnya "kenapa tenant ini
            // ditangguhkan" hanya bisa dijawab dengan membuka dua halaman lalu
            // mencocokkan namanya sendiri.
            { name: 'Langganan & Tagihan', href: '/platform/subscriptions', icon: 'credit-card', modules: ['subscriptions', 'payments'] },
            { name: 'Aturan Harga', href: '/platform/pricing-rules', icon: 'tag', modules: ['pricing_rules'] },
            // Di bawah Komersial, bukan Sistem: yang diatur di sana adalah
            // seberapa besar tagihan kunci bersama yang ditanggung pemilik SaaS,
            // dan itu urusan uang — bukan urusan teknis.
            { name: 'Kuota AI', href: '/platform/ai-quota', icon: 'sparkles', modules: ['ai_quota'] },
        ],
    },
    {
        label: 'Sistem',
        items: [
            { name: 'Jejak Audit', href: '/platform/audit-logs', icon: 'clipboard', modules: ['audit_logs'] },
            { name: 'Akun Platform', href: '/platform/users', icon: 'users', ownerOnly: true },
            // Tanpa `modules` dan tanpa `ownerOnly` ([BL-013]): keamanan akun
            // sendiri bukan modul yang bisa dipegangkan atau ditahan. Staf
            // platform yang tidak dipegangi satu modul pun tetap harus bisa
            // mengamankan akunnya.
            { name: 'Keamanan Akun', href: '/platform/keamanan/two-factor', icon: 'shield' },
        ],
    },
];

const canShowItem = (item) => {
    if (item.ownerOnly) {
        return platformUser.value?.is_owner === true;
    }

    if (!item.modules) {
        return true;
    }

    return item.modules.some((module) => modules.value.includes(module));
};

const visibleGroups = computed(() =>
    sidebarGroups
        .map((group) => ({ ...group, items: group.items.filter(canShowItem) }))
        .filter((group) => group.items.length),
);

const isActive = (href) => {
    if (href === '/platform') {
        return page.url === '/platform';
    }

    return page.url.startsWith(href);
};

// Bagian + nama halaman diturunkan dari item nav yang aktif, sama seperti
// OwnerLayout. Halaman rincian menimpanya lewat slot #header.
const breadcrumb = computed(() => {
    for (const group of sidebarGroups) {
        for (const item of group.items) {
            if (isActive(item.href)) {
                return { group: group.label, name: item.name };
            }
        }
    }

    return { group: null, name: 'Beranda' };
});

const userInitial = computed(() => platformUser.value?.name?.charAt(0)?.toUpperCase() ?? 'P');

// Konsol platform tidak menyimpan cache offline milik penyewa, jadi yang
// hilang di sini hanya sesinya — kalimatnya ikut lebih pendek.
const { requestLogout } = useLogoutConfirm();

const logout = () => requestLogout({
    endpoint: '/platform/logout',
    clearOfflineData: false,
    title: 'Keluar dari konsol platform?',
    message: 'Sesi admin platform Anda ditutup dan Anda harus masuk lagi untuk melanjutkan.',
});

// Sesi akun dipindah dari footer sidebar ke topbar, meniru trigger avatar +
// dropdown di CashierTopbar — satu pola yang sama untuk menu akun di seluruh
// aplikasi, alih-alih tombol keluar telanjang yang tersembunyi di sidebar.
const accountMenuOpen = ref(false);
const accountMenuRef = ref(null);
const toggleAccountMenu = () => { accountMenuOpen.value = !accountMenuOpen.value; };

const handleClickOutsideAccountMenu = (e) => {
    if (accountMenuRef.value && !accountMenuRef.value.contains(e.target)) {
        accountMenuOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutsideAccountMenu));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutsideAccountMenu));
</script>

<template>
    <div class="h-screen overflow-hidden bg-background lg:flex">

        <!-- Mobile backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-black/30 lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- ── Sidebar ──────────────────────────────────────────────────── -->
        <aside
            id="platform-sidebar"
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-card border-r border-border flex flex-col overflow-hidden',
                'transition-[transform,width] lg:static lg:inset-0 lg:shrink-0 lg:h-screen',
                sidebarOpen
                    ? 'translate-x-0 shadow-lg lg:translate-x-0 lg:w-64 lg:shadow-none'
                    : '-translate-x-full lg:translate-x-0 lg:w-14',
            ]"
            :style="{ transitionDuration: '240ms', transitionTimingFunction: 'cubic-bezier(0.25, 1, 0.5, 1)' }"
            aria-label="Sidebar navigasi platform"
        >
            <!-- Brand — struktur sama dengan panel tenant, warnanya saja yang
                 dibedakan (slate, bukan primary) supaya sekali lihat jelas
                 panel mana yang sedang dibuka. -->
            <div
                class="flex items-center h-14 border-b border-border flex-shrink-0 transition-[padding] duration-[240ms]"
                :class="sidebarOpen ? 'justify-between px-4' : 'justify-center px-0'"
            >
                <div class="flex items-center gap-2.5 min-w-0">
                    <div
                        class="w-7 h-7 rounded-lg bg-slate-800 flex items-center justify-center flex-shrink-0"
                        aria-hidden="true"
                    >
                        <span class="text-white text-[11px] font-bold tracking-tight select-none">SP</span>
                    </div>
                    <div v-show="sidebarOpen" class="leading-none min-w-0">
                        <span class="block text-[1.0625rem] font-bold text-foreground tracking-tight select-none">SAPI</span>
                        <span class="block text-[0.625rem] font-semibold text-muted-foreground uppercase tracking-widest mt-0.5">
                            Platform
                        </span>
                    </div>
                </div>

                <button
                    v-show="sidebarOpen"
                    type="button"
                    class="w-7 h-7 flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors duration-150 flex-shrink-0"
                    aria-label="Tutup sidebar"
                    @click="sidebarOpen = false"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3 px-3 scrollbar-sidebar" aria-label="Navigasi platform">
                <template v-for="(group, gIdx) in visibleGroups" :key="gIdx">
                    <button
                        v-if="group.label && sidebarOpen"
                        type="button"
                        class="w-full flex items-center justify-between px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground hover:text-foreground transition-colors duration-150 select-none"
                        :class="gIdx === 0 ? 'pt-2' : 'pt-5'"
                        :aria-expanded="!collapsedGroups[group.label]"
                        @click="toggleGroup(group.label)"
                    >
                        <span>{{ group.label }}</span>
                        <svg
                            class="w-3.5 h-3.5 transition-transform duration-200"
                            :class="collapsedGroups[group.label] ? '-rotate-90' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div
                        class="overflow-hidden transition-[max-height] duration-200 ease-in-out"
                        :class="group.label && collapsedGroups[group.label] && sidebarOpen ? 'max-h-0' : 'max-h-96'"
                    >
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :title="!sidebarOpen ? item.name : undefined"
                            :class="[
                                'flex items-center rounded-md text-sm transition-colors duration-150 mb-0.5 h-9',
                                sidebarOpen ? 'gap-2.5 px-3' : 'justify-center px-0',
                                isActive(item.href)
                                    ? 'bg-primary/10 text-primary font-medium'
                                    : 'text-foreground/60 font-normal hover:bg-muted hover:text-foreground',
                            ]"
                            @click="handleNavClick"
                        >
                            <NavIcon :name="item.icon" />
                            <span v-show="sidebarOpen" class="whitespace-nowrap">{{ item.name }}</span>
                        </Link>
                    </div>
                </template>
            </nav>
        </aside>

        <!-- ── Main ─────────────────────────────────────────────────────── -->
        <div class="lg:flex-1 lg:min-w-0 flex flex-col h-screen overflow-hidden">
            <header class="relative z-30 bg-card border-b border-border shadow-[0_1px_2px_0_rgba(0,0,0,0.05)] flex-shrink-0">
                <div class="flex items-center justify-between h-14 px-4 sm:px-5">
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            type="button"
                            class="w-8 h-8 flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors duration-150 flex-shrink-0"
                            aria-label="Buka/tutup sidebar"
                            :aria-expanded="sidebarOpen"
                            aria-controls="platform-sidebar"
                            @click="toggleSidebar"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <nav class="flex items-center gap-1.5 min-w-0" aria-label="Breadcrumb">
                            <template v-if="breadcrumb.group">
                                <span class="hidden sm:inline text-sm text-muted-foreground truncate">{{ breadcrumb.group }}</span>
                                <span class="hidden sm:inline text-muted-foreground/50" aria-hidden="true">&rsaquo;</span>
                            </template>
                            <h1 class="text-sm font-semibold text-foreground truncate">
                                <slot name="header">{{ breadcrumb.name }}</slot>
                            </h1>
                        </nav>
                    </div>

                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="hidden md:block text-xs text-muted-foreground">
                            Konsol pemilik layanan
                        </span>

                        <!-- Account menu: avatar + dropdown, sama polanya dengan CashierTopbar -->
                        <div ref="accountMenuRef" class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 pl-1.5 pr-2.5 h-9 text-sm rounded-md border transition-colors duration-150"
                                :class="accountMenuOpen ? 'bg-muted border-border' : 'border-border hover:bg-muted'"
                                :aria-expanded="accountMenuOpen"
                                aria-haspopup="true"
                                aria-label="Menu akun"
                                @click="toggleAccountMenu"
                            >
                                <span class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                                    <span class="text-white text-xs font-semibold leading-none select-none">{{ userInitial }}</span>
                                </span>
                                <span class="hidden sm:inline text-foreground/70 max-w-[140px] truncate">{{ platformUser?.name }}</span>
                                <NavIcon name="chevron" class="w-3.5 h-3.5 text-foreground/40 flex-shrink-0 transition-transform duration-150" :class="{ 'rotate-180': accountMenuOpen }" />
                            </button>

                            <Transition
                                enter-active-class="transition duration-100 ease-out"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition duration-75 ease-in"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div
                                    v-if="accountMenuOpen"
                                    class="absolute right-0 top-full mt-1.5 w-56 bg-card border border-border rounded-lg shadow-lg z-50 overflow-hidden origin-top-right"
                                    role="menu"
                                >
                                    <div class="px-3 py-2.5 border-b border-border">
                                        <p class="text-sm font-medium text-foreground truncate">{{ platformUser?.name }}</p>
                                        <p class="text-[11px] text-muted-foreground truncate mt-0.5">
                                            {{ platformUser?.is_owner ? 'Pemilik platform' : 'Staf platform' }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="w-full text-left px-3 py-2 text-sm text-destructive hover:bg-destructive/10 flex items-center gap-2 transition-colors duration-150"
                                        role="menuitem"
                                        @click="logout"
                                    >
                                        <NavIcon name="logout" />
                                        Keluar
                                    </button>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </header>

            <FlashMessage />
            <LogoutConfirmDialog />

            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-7 scrollbar-main">
                <slot />
            </main>
        </div>
    </div>
</template>
