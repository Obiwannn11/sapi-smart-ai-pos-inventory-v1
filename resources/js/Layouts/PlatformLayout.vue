<script setup>
import { usePage, router, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { computed, ref } from 'vue';

const page = usePage();

// Panel ini memakai auth.platformUser, BUKAN auth.user. Keduanya sengaja
// dipisah di HandleInertiaRequests: akun platform tidak punya role maupun
// tenant_id, jadi menumpangkannya di auth.user akan mengirim null diam-diam ke
// komponen yang mengharapkan data tenant.
const platformUser = computed(() => page.props.auth.platformUser);
const modules = computed(() => platformUser.value?.modules ?? []);

const sidebarOpen = ref(true);

// Nav difilter dari daftar modul yang sama dengan gerbang route
// (config/platform-rbac.php) — satu sumber kebenaran, bukan dua daftar.
// `ownerOnly` untuk hal yang dijaga penanda is_owner, bukan modul grantable.
const navItems = [
    { name: 'Beranda', href: '/platform', module: null },
    { name: 'Daftar Tenant', href: '/platform/tenants', module: 'tenants' },
    { name: 'Langganan', href: '/platform/subscriptions', module: 'subscriptions' },
    { name: 'Pembayaran', href: '/platform/invoices', module: 'payments' },
    { name: 'Aturan Harga', href: '/platform/pricing-rules', module: 'pricing_rules' },
    { name: 'Jejak Audit', href: '/platform/audit-logs', module: 'audit_logs' },
    { name: 'Akun Platform', href: '/platform/users', ownerOnly: true },
];

const visibleNav = computed(() =>
    navItems.filter((item) => {
        if (item.ownerOnly) return platformUser.value?.is_owner;
        return !item.module || modules.value.includes(item.module);
    }),
);

const isActive = (href) => {
    if (href === '/platform') return page.url === '/platform';
    return page.url.startsWith(href);
};

const userInitial = computed(() => platformUser.value?.name?.charAt(0)?.toUpperCase() ?? 'P');

const logout = () => router.post('/platform/logout');
</script>

<template>
    <div class="h-screen overflow-hidden bg-background lg:flex">

        <!-- Mobile backdrop -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/30 lg:hidden"
            aria-hidden="true"
            @click="sidebarOpen = false"
        />

        <!-- ── Sidebar ──────────────────────────────────────────────────── -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-card border-r border-border flex flex-col',
                'transition-transform lg:static lg:inset-0 lg:shrink-0 lg:h-screen lg:translate-x-0',
                sidebarOpen ? 'translate-x-0 shadow-lg lg:shadow-none' : '-translate-x-full',
            ]"
            aria-label="Sidebar navigasi platform"
        >
            <!-- Brand — sengaja dibedakan dari panel tenant (slate, bukan primary)
                 supaya sekali lihat jelas panel mana yang sedang dibuka. -->
            <div class="flex items-center gap-2.5 h-14 px-4 border-b border-border flex-shrink-0">
                <div
                    class="w-7 h-7 rounded-lg bg-slate-800 flex items-center justify-center flex-shrink-0"
                    aria-hidden="true"
                >
                    <span class="text-white text-[11px] font-bold tracking-tight select-none">SP</span>
                </div>
                <div class="leading-none">
                    <span class="block text-[0.9375rem] font-bold text-foreground tracking-tight">SAPI</span>
                    <span class="block text-[0.625rem] font-semibold text-muted-foreground uppercase tracking-widest mt-0.5">
                        Platform
                    </span>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3">
                <ul class="space-y-0.5 px-2">
                    <li v-for="item in visibleNav" :key="item.href">
                        <Link
                            :href="item.href"
                            :class="[
                                'block rounded-lg px-3 py-2 text-sm transition-colors',
                                isActive(item.href)
                                    ? 'bg-accent text-foreground font-medium'
                                    : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                            ]"
                        >
                            {{ item.name }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- Account -->
            <div class="border-t border-border p-3 flex items-center gap-2.5">
                <div
                    class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-semibold flex-shrink-0"
                    aria-hidden="true"
                >
                    {{ userInitial }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-foreground truncate">{{ platformUser?.name }}</p>
                    <p class="text-xs text-muted-foreground truncate">{{ platformUser?.email }}</p>
                </div>
                <button
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground transition-colors"
                    @click="logout"
                >
                    Keluar
                </button>
            </div>
        </aside>

        <!-- ── Main ─────────────────────────────────────────────────────── -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
            <header class="h-14 bg-card border-b border-border flex items-center gap-3 px-4 flex-shrink-0">
                <button
                    type="button"
                    class="lg:hidden text-muted-foreground hover:text-foreground"
                    aria-label="Buka navigasi"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-sm font-semibold text-foreground">
                    <slot name="header">Platform Console</slot>
                </h1>
            </header>

            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                <FlashMessage />
                <slot />
            </main>
        </div>
    </div>
</template>
