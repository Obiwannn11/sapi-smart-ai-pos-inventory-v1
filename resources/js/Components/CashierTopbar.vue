<script setup>
import { computed, h, defineComponent } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'SAPI POS' },
});

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const userName = computed(() => user.value?.name ?? '');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase() || 'U');
const isOwner = computed(() => user.value?.role === 'owner');

const isActive = (href) => page.url.startsWith(href);

// Cash drawer is cashier-only — owner never manages kas.
const navItems = computed(() => {
    const items = [
        { name: 'POS', href: '/cashier/pos', icon: 'pos' },
        { name: 'Riwayat', href: '/cashier/transactions', icon: 'history' },
    ];
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
const btnLogout = 'border-destructive/30 text-destructive hover:bg-destructive/10';

const logout = () => router.post('/logout');
</script>

<template>
    <header class="bg-card border-b border-border shadow-[0_1px_2px_0_rgba(0,0,0,0.05)] px-4 py-2.5 flex items-center justify-between shrink-0 z-10">
        <!-- Left: brand + cashier identity -->
        <div class="flex items-center gap-3 min-w-0">
            <h1 class="text-lg font-bold text-primary truncate">{{ title }}</h1>
            <span class="hidden sm:inline-flex items-center gap-2 pl-3 border-l border-border min-w-0">
                <span class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                    <span class="text-primary text-xs font-semibold leading-none select-none">{{ userInitial }}</span>
                </span>
                <span class="text-sm text-foreground/70 truncate">{{ userName }}</span>
            </span>
        </div>

        <!-- Right: nav as buttons, ordered by cashier flow -->
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

            <button :class="[btnBase, btnLogout]" @click="logout" aria-label="Keluar dari akun">
                <NavIcon name="logout" />
                <span class="hidden sm:inline">Logout</span>
            </button>
        </nav>
    </header>
</template>
