{{--
    Palet bersama tiga permukaan publik: `/`, `/api-docs`, dan `/dokumentasi`.

    Sebelumnya token ini ditulis dua kali — sekali di `api-docs.blade.php` dan
    sekali lagi disalin ke `docs/layout.blade.php`, yang komentarnya sendiri
    mengakui itu salinan. Landing tidak punya token ini sama sekali dan memakai
    warna Tailwind bawaan, sehingga ketiganya berangkat dari tiga sumber gaya
    berbeda.

    Kenapa partial Blade dan BUKAN dipindahkan ke `resources/css/app.css`:
    nama `--border` di sini bertabrakan dengan token bernama sama milik shell
    aplikasi (`app.css`), yang dipetakan `@theme inline` jadi utilitas
    `border-border` dan dipakai seluruh halaman Vue di balik login. Menaruh
    nilai publik di `:root` global berarti menggeser tampilan setiap layar
    aplikasi demi tiga halaman publik. Partial ini menjaganya tetap sebatas
    permukaan yang memang memakainya.

    Token khusus komponen — badge metode HTTP dan blok kode di `/api-docs` —
    sengaja TIDAK diangkat ke sini: keduanya hanya dipakai satu permukaan.
--}}
<style>
    :root {
        /* Latar */
        --bg:            oklch(0.97 0.006 150);
        --bg-card:       oklch(0.993 0.004 150);
        --bg-surface:    oklch(0.94 0.008 150);

        /* Hijau merek */
        --green:         oklch(0.58 0.128 162);
        --green-cta:     oklch(0.51 0.12 162);
        --green-hover:   oklch(0.46 0.115 162);
        --green-faint:   oklch(0.93 0.035 162);

        /* Teks */
        --text:          oklch(0.23 0.015 160);
        --text-muted:    oklch(0.48 0.012 160);
        --text-dim:      oklch(0.62 0.010 155);

        /* Garis */
        --border:        oklch(0.88 0.010 150);
        --border-faint:  oklch(0.92 0.008 150);

        /* Peringatan */
        --red-soft:      oklch(0.58 0.18 27);
    }
</style>
