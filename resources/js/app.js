import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    title: (title) => title ? `${title} — SAPI` : 'SAPI',
    // Glob malas ([BL-094]): tiap halaman jadi chunk-nya sendiri, bukan satu
    // bundel entry berisi 56 halaman. Konsekuensinya resolusi komponen jadi
    // asinkron — perpindahan halaman menambah satu permintaan jaringan yang
    // sebelumnya tidak ada. Lihat catatan `delay` pada `progress` di bawah.
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
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
    //
    // Yang berubah oleh [BL-094]: kerangka pemuatan tidak lagi yang pertama
    // sampai. Inertia menunggu `resolve` selesai sebelum menukar halaman, jadi
    // pada cache dingin unduhan chunk halaman (2–15 berkas, 2–229 KB di luar
    // entry) kini berada DI DALAM jendela bilah ini — kerangkanya baru berdiri
    // sesudah chunk-nya tiba. Angka 500 sengaja tidak diubah: pada sambungan
    // wajar tambahannya puluhan milidetik dan bilah tetap tak sempat lahir,
    // sedangkan pada sambungan lambat bilah inilah yang memang dibutuhkan.
    // Kalimat "jarang terlihat" di atas kini bertumpu pada chunk yang sudah
    // ter-cache, bukan lagi pada bundel tunggal yang selalu ada.
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
