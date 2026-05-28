<script setup>
import { usePage, router, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { ref, computed, h, defineComponent } from 'vue';

const page = usePage();
const { auth } = page.props;

// Sidebar starts open on desktop, closed on mobile. Toggled by the topbar
// hamburger at any screen size.
const isDesktop = typeof window !== 'undefined' && window.innerWidth >= 1024;
const sidebarOpen = ref(isDesktop);
const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value; };

// On mobile the sidebar is an overlay, so close it after navigating.
// On desktop it stays open (push layout).
const handleNavClick = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
        sidebarOpen.value = false;
    }
};

// Collapsible nav groups (keyed by group label). Expanded by default.
const collapsedGroups = ref({});
const toggleGroup = (label) => {
    collapsedGroups.value[label] = !collapsedGroups.value[label];
};

// ── Centralised icon renderer ──────────────────────────────────────────────
// All sidebar SVG paths in one place. Arrays = multiple <path> elements.
const iconPaths = {
    home: [
        'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z',
        'M9 22V12h6v10',
    ],
    folder:
        'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    cube: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    archive:
        'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
    adjustments:
        'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
    report:
        'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    receipt:
        'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
    cash: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    'credit-card':
        'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    'office-building': [
        'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5',
        'M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    ],
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

// ── Navigation ─────────────────────────────────────────────────────────────
const sidebarGroups = [
    {
        label: null,
        items: [{ name: 'Beranda', href: '/owner/dashboard', icon: 'home' }],
    },
    {
        label: 'Atur Menu',
        items: [
            { name: 'Kategori', href: '/owner/categories', icon: 'folder' },
            { name: 'Produk', href: '/owner/products', icon: 'cube' },
            { name: 'Stok', href: '/owner/stock', icon: 'archive' },
            { name: 'Modifier', href: '/owner/modifiers', icon: 'adjustments' },
        ],
    },
    {
        label: 'Keuangan',
        items: [
            { name: 'Laporan Harian', href: '/owner/reports/daily', icon: 'report' },
            { name: 'Transaksi', href: '/owner/transactions', icon: 'receipt' },
            { name: 'Sesi Kas', href: '/owner/cash-drawers', icon: 'cash' },
            { name: 'Pembayaran', href: '/owner/payment-methods', icon: 'credit-card' },
        ],
    },
    {
        label: 'Pengaturan',
        items: [
            { name: 'Profil Usaha', href: '/owner/settings', icon: 'office-building' },
        ],
    },
];

const isActive = (href) => {
    const url = page.url;
    if (href === '/owner/dashboard') return url === '/owner/dashboard';
    return url.startsWith(href);
};

// Derives the current section + page name from the active nav item.
const breadcrumb = computed(() => {
    for (const group of sidebarGroups) {
        for (const item of group.items) {
            if (isActive(item.href)) return { group: group.label, name: item.name };
        }
    }
    return { group: null, name: 'Beranda' };
});

const todayLabel = computed(() =>
    new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const userInitial = computed(() =>
    auth.user?.name?.charAt(0)?.toUpperCase() ?? 'U',
);

const roleLabel = computed(() => {
    const map = { owner: 'Pemilik', cashier: 'Kasir', admin: 'Admin' };
    return map[auth.user?.role] ?? auth.user?.role ?? '';
});

const logout = () => router.post('/logout');
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
            id="main-sidebar"
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-card border-r border-border flex flex-col overflow-hidden',
                'transition-[transform,width] lg:static lg:inset-0 lg:shrink-0 lg:h-screen',
                sidebarOpen
                    ? 'translate-x-0 shadow-lg lg:translate-x-0 lg:w-64 lg:shadow-none'
                    : '-translate-x-full lg:translate-x-0 lg:w-14',
            ]"
            :style="{ transitionDuration: '240ms', transitionTimingFunction: 'cubic-bezier(0.25, 1, 0.5, 1)' }"
            aria-label="Sidebar navigasi"
        >
            <!-- Brand mark -->
            <div
                class="flex items-center h-14 border-b border-border flex-shrink-0 transition-[padding,justify-content] duration-[240ms]"
                :class="sidebarOpen ? 'justify-between px-4' : 'justify-center px-0'"
            >
                <div class="flex items-center gap-2.5">
                    <!-- S glyph (always visible) -->
                    <div
                        class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center flex-shrink-0"
                        aria-hidden="true"
                    >
                        <span class="text-primary-foreground text-[11px] font-bold tracking-tight select-none">S</span>
                    </div>
                    <!-- SAPI text — hidden in icon-only rail mode -->
                    <span
                        v-show="sidebarOpen"
                        class="text-[1.0625rem] font-bold text-brand tracking-tight select-none whitespace-nowrap"
                    >SAPI</span>
                </div>

                <!-- Close button — hidden in icon-only rail mode -->
                <button
                    v-show="sidebarOpen"
                    @click="sidebarOpen = false"
                    class="w-7 h-7 flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors duration-150 flex-shrink-0"
                    aria-label="Tutup sidebar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Nav items -->
            <nav class="flex-1 overflow-y-auto py-3 px-3 scrollbar-sidebar" aria-label="Navigasi utama">
                <template v-for="(group, gIdx) in sidebarGroups" :key="gIdx">
                    <!-- Group label — collapsible toggle; hidden in icon-only rail mode -->
                    <button
                        v-if="group.label && sidebarOpen"
                        type="button"
                        @click="toggleGroup(group.label)"
                        class="w-full flex items-center justify-between px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground hover:text-foreground transition-colors duration-150 select-none"
                        :class="gIdx === 0 ? 'pt-2' : 'pt-5'"
                        :aria-expanded="!collapsedGroups[group.label]"
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

                    <!-- Group items — collapse only when sidebar is open; always visible in icon-only rail -->
                    <div
                        class="overflow-hidden transition-[max-height] duration-200 ease-in-out"
                        :class="group.label && collapsedGroups[group.label] && sidebarOpen ? 'max-h-0' : 'max-h-96'"
                    >
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            @click="handleNavClick"
                            :title="!sidebarOpen ? item.name : undefined"
                            :class="[
                                'flex items-center rounded-md text-sm transition-colors duration-150 mb-0.5 h-9',
                                sidebarOpen ? 'gap-2.5 px-3' : 'justify-center px-0',
                                isActive(item.href)
                                    ? 'bg-primary/10 text-primary font-medium'
                                    : 'text-foreground/60 font-normal hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <NavIcon :name="item.icon" />
                            <span v-show="sidebarOpen" class="whitespace-nowrap">{{ item.name }}</span>
                        </Link>
                    </div>
                </template>
            </nav>

            <!-- User footer -->
            <div class="flex-shrink-0 border-t border-border p-3">
                <div
                    class="flex items-center min-w-0"
                    :class="sidebarOpen ? 'gap-2.5 px-1' : 'justify-center px-0'"
                >
                    <!-- Avatar (always visible) -->
                    <div
                        class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0"
                        :title="!sidebarOpen ? auth.user.name : undefined"
                        aria-hidden="true"
                    >
                        <span class="text-primary text-sm font-semibold leading-none select-none">{{ userInitial }}</span>
                    </div>

                    <!-- Name + role — hidden in icon-only rail mode -->
                    <div v-show="sidebarOpen" class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-foreground truncate leading-snug">{{ auth.user.name }}</p>
                        <p class="text-[11px] text-muted-foreground leading-snug">{{ roleLabel }}</p>
                    </div>

                    <!-- Logout — hidden in icon-only rail mode -->
                    <button
                        v-show="sidebarOpen"
                        @click="logout"
                        class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors duration-150"
                        title="Keluar"
                        aria-label="Keluar dari akun"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ── Main area ─────────────────────────────────────────────────── -->
        <div class="lg:flex-1 lg:min-w-0 flex flex-col h-screen overflow-hidden">

            <!-- Top bar -->
            <header class="relative z-30 bg-card border-b border-border shadow-[0_1px_2px_0_rgba(0,0,0,0.05)] flex-shrink-0">
                <div class="flex items-center justify-between h-14 px-4 sm:px-5">

                    <!-- Left: hamburger + page title -->
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            @click="toggleSidebar"
                            class="w-8 h-8 flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors duration-150 flex-shrink-0"
                            aria-label="Buka/tutup sidebar"
                            :aria-expanded="sidebarOpen"
                            aria-controls="main-sidebar"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <nav class="flex items-center gap-1.5 min-w-0" aria-label="Breadcrumb">
                            <template v-if="breadcrumb.group">
                                <span class="hidden sm:inline text-sm text-muted-foreground truncate">{{ breadcrumb.group }}</span>
                                <span class="hidden sm:inline text-muted-foreground/50" aria-hidden="true">&rsaquo;</span>
                            </template>
                            <h1 class="text-sm font-semibold text-foreground truncate">{{ breadcrumb.name }}</h1>
                        </nav>
                    </div>

                    <!-- Right: date + Kasir shortcut -->
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="hidden md:block text-xs text-muted-foreground">{{ todayLabel }}</span>
                        <Link
                            href="/cashier/pos"
                            class="inline-flex items-center gap-1.5 px-3 h-8 text-xs font-medium rounded-md border border-border text-foreground/60 hover:bg-muted hover:text-foreground transition-colors duration-150"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Kasir
                        </Link>
                    </div>
                </div>
            </header>

            <FlashMessage />

            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-7 scrollbar-main">
                <slot />
            </main>
        </div>
    </div>
</template>
