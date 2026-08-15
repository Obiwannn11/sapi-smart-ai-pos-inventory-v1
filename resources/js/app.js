import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => title ? `${title} — SAPI` : 'SAPI',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    // Bilah kemajuan ([BL-037]). Tidak dimatikan, tapi dibuat jarang terlihat:
    // sejak setiap halaman berdaftar panjang punya kerangka pemuatan, respons
    // pertamanya sampai dalam hitungan puluhan milidetik dan bilah ini tidak
    // sempat muncul sama sekali. Yang tersisa untuknya adalah dua hal yang
    // memang tidak bisa dijawab kerangka: pengiriman formulir (POST/PUT — tidak
    // ada prop tertunda di sana, jadi tidak ada tempat kerangka berdiri) dan
    // sambungan yang benar-benar lambat. `delay: 500` membuatnya hanya lahir
    // untuk kedua keadaan itu — bukan untuk setiap ketukan menu.
    progress: {
        color: 'var(--primary)',
        delay: 500,
    },
});

// Register the PWA service worker (production only — avoids interfering with Vite HMR in dev).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((err) => {
            console.error('Service worker registration failed:', err);
        });
    });
}
