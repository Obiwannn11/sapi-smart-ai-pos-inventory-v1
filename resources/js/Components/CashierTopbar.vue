<script setup>
import { computed, h, ref, onMounted, onBeforeUnmount, defineComponent } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import PrinterSetupModal from '@/Components/PrinterSetupModal.vue';
import { useInstallPrompt } from '@/composables/useInstallPrompt';
import { clearPrivateOfflineData } from '@/services/offlineSession';

defineProps({
    title: { type: String, default: 'SAPI POS' },
});

const page = usePage();

const showPrinterSetup = ref(false);
const { canInstall, promptInstall } = useInstallPrompt();

const user = computed(() => page.props.auth?.user ?? null);
const userName = computed(() => user.value?.name ?? '');
const userEmail = computed(() => user.value?.email ?? '');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase() || 'U');
const isOwner = computed(() => user.value?.role === 'owner');

// Owner-side modules this staff may open (Keputusan A: entry point into the
// shared filtered sidebar shell). POS/cash-drawer live in the cashier shell and
// are intentionally excluded here.
const ownerModuleCatalog = [
    { perm: 'products', label: 'Produk', href: '/owner/products' },
    { perm: 'stock', label: 'Stok', href: '/owner/stock' },
    { perm: 'reports', label: 'Laporan', href: '/owner/reports/daily' },
    { perm: 'payment_methods', label: 'Pembayaran', href: '/owner/payment-methods' },
    { perm: 'ai_analysis', label: 'AI Analysis', href: '/owner/ai-analysis' },
];
const staffModules = computed(() => {
    const perms = user.value?.permissions ?? [];
    return ownerModuleCatalog.filter((m) => perms.includes(m.perm));
});

const isActive = (href) => page.url.startsWith(href);

// POS/kasir is reachable via the back button on the left, so it's omitted here.
// Kapabilitas outlet, dibagikan lewat HandleInertiaRequests. Gerbang rutenya
// tetap sumber kebenaran — tautan ini hanya cermin.
const hasFeature = (feature) => page.props.auth?.tenant?.features?.[feature] === true;

const navItems = computed(() => {
    const items = [];

    if (hasFeature('kitchen_queue')) {
        items.push({ name: 'Antrian', href: '/cashier/queue', icon: 'queue' });
    }

    items.push({ name: 'Riwayat', href: '/cashier/transactions', icon: 'history' });

    if (!isOwner.value) {
        items.push({ name: 'Kas', href: '/cashier/cash-drawer', icon: 'cash' });
    }
    return items;
});

const iconPaths = {
    pos: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
    history: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    cash: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    logout: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    dashboard: 'M4 5a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM14 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4z',
    chevron: 'M19 9l-7 7-7-7',
    back: 'M10 19l-7-7m0 0l7-7m-7 7h18',
    printer: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
    install: 'M12 4v12m0 0l-4-4m4 4l4-4M4 20h16',
    queue: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
};

const NavIcon = defineComponent({
    props: { name: { type: String, required: true } },
    setup(props) {
        return () =>
            h(
                'svg',
                {
                    class: 'w-4 h-4 flex-shrink-0',
                    fill: 'none',
                    stroke: 'currentColor',
                    viewBox: '0 0 24 24',
                    'aria-hidden': 'true',
                },
                [
                    h('path', {
                        'stroke-linecap': 'round',
                        'stroke-linejoin': 'round',
                        'stroke-width': '2',
                        d: iconPaths[props.name] ?? iconPaths.pos,
                    }),
                ],
            );
    },
});

const btnBase =
    'inline-flex items-center gap-1.5 px-3 h-9 text-sm font-medium rounded-md border transition-colors duration-150';
const btnInactive = 'border-border text-foreground/70 hover:bg-muted hover:text-foreground';
const btnActive = 'bg-primary/10 text-primary border-primary/30';

const dropdownOpen = ref(false);
const dropdownRef = ref(null);

const toggleDropdown = () => { dropdownOpen.value = !dropdownOpen.value; };

const handleClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        dropdownOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutside));

// Drop cached POS page + catalog before leaving: the till is shared hardware.
const logout = async () => {
    await clearPrivateOfflineData();
    router.post('/logout');
};
</script>

<template>
    <header class="bg-card border-b border-border shadow-[0_1px_2px_0_rgba(0,0,0,0.05)] px-4 py-2.5 flex items-center justify-between shrink-0 z-10">
        <!-- Left: back-to-POS + brand -->
        <div class="flex items-center gap-2 min-w-0">
            <Link
                v-if="!isActive('/cashier/pos')"
                href="/cashier/pos"
                :class="[btnBase, btnInactive, 'px-2']"
                title="Kembali ke Kasir"
                aria-label="Kembali ke Kasir"
            >
                <NavIcon name="back" />
                <span class="hidden sm:inline">Kasir</span>
            </Link>
            <h1 class="text-lg font-bold text-primary truncate">{{ title }}</h1>
        </div>

        <!-- Right: nav buttons + user avatar dropdown -->
        <div class="flex items-center gap-2">
            <!-- Install PWA (shown only when the browser offers it) -->
            <button
                v-if="canInstall"
                @click="promptInstall"
                :class="[btnBase, btnInactive]"
                title="Install aplikasi"
                aria-label="Install aplikasi"
            >
                <NavIcon name="install" />
                <span class="hidden sm:inline">Install</span>
            </button>

            <!-- Printer settings -->
            <button
                @click="showPrinterSetup = true"
                :class="[btnBase, btnInactive]"
                title="Pengaturan printer"
                aria-label="Pengaturan printer"
            >
                <NavIcon name="printer" />
            </button>

            <nav class="flex items-center gap-2" aria-label="Navigasi kasir">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :class="[btnBase, isActive(item.href) ? btnActive : btnInactive]"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >
                    <NavIcon :name="item.icon" />
                    <span class="hidden sm:inline">{{ item.name }}</span>
                </Link>
            </nav>

            <!-- User avatar + dropdown -->
            <div ref="dropdownRef" class="relative">
                <button
                    @click="toggleDropdown"
                    :class="[btnBase, dropdownOpen ? 'bg-muted border-border' : 'border-border hover:bg-muted']"
                    :aria-expanded="dropdownOpen"
                    aria-haspopup="true"
                    aria-label="Menu akun"
                >
                    <span class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                        <span class="text-primary text-xs font-semibold leading-none select-none">{{ userInitial }}</span>
                    </span>
                    <span class="hidden sm:inline text-sm text-foreground/70 max-w-[120px] truncate">{{ userName }}</span>
                    <svg class="w-3.5 h-3.5 text-foreground/40 flex-shrink-0 transition-transform duration-150" :class="{ 'rotate-180': dropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown panel -->
                <Transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="dropdownOpen"
                        class="absolute right-0 top-full mt-1.5 w-52 bg-card border border-border rounded-lg shadow-lg z-50 overflow-hidden origin-top-right"
                        role="menu"
                    >
                        <!-- Account info -->
                        <div class="px-3 py-2.5 border-b border-border">
                            <p class="text-sm font-medium text-foreground truncate">{{ userName }}</p>
                            <p class="text-xs text-foreground/50 truncate mt-0.5">{{ userEmail }}</p>
                        </div>

                        <!-- Owner dashboard (owner only) -->
                        <Link
                            v-if="isOwner"
                            href="/owner/dashboard"
                            class="w-full text-left px-3 py-2 text-sm text-foreground/80 hover:bg-muted flex items-center gap-2 transition-colors duration-150"
                            role="menuitem"
                            @click="dropdownOpen = false"
                        >
                            <NavIcon name="dashboard" />
                            Dashboard Owner
                        </Link>

                        <!-- Staff module access (non-owner staff with granted modules) -->
                        <template v-if="!isOwner && staffModules.length">
                            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-foreground/40 border-t border-border">
                                Kelola Toko
                            </div>
                            <Link
                                v-for="mod in staffModules"
                                :key="mod.href"
                                :href="mod.href"
                                class="w-full text-left px-3 py-2 text-sm text-foreground/80 hover:bg-muted flex items-center gap-2 transition-colors duration-150"
                                role="menuitem"
                                @click="dropdownOpen = false"
                            >
                                <NavIcon name="dashboard" />
                                {{ mod.label }}
                            </Link>
                        </template>

                        <!-- Logout -->
                        <button
                            @click="logout"
                            class="w-full text-left px-3 py-2 text-sm text-destructive hover:bg-destructive/10 flex items-center gap-2 transition-colors duration-150"
                            role="menuitem"
                        >
                            <NavIcon name="logout" />
                            Logout
                        </button>
                    </div>
                </Transition>
            </div>
        </div>

        <PrinterSetupModal :show="showPrinterSetup" @close="showPrinterSetup = false" />
    </header>
</template>
