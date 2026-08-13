{{--
    Kerangka bersama halaman dokumentasi.

    Paletnya kini datang dari `public/partials/theme`, dipakai bersama landing
    dan /api-docs — sebelumnya ia salinan dari berkas api-docs. CSS tata letak
    di bawah tetap tinggal di sini, bukan diambil dari berkas itu: berkas itu
    1800 baris dan memuat komponen khusus endpoint yang tidak dipakai halaman
    prosa.
--}}
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — Dokumentasi SAPI</title>
    <meta name="description" content="@yield('description', 'Dokumentasi SAPI — panduan penggunaan aplikasi kasir dan dokumentasi developer.')">

    @include('public.partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css'])

    @include('public.partials.theme')

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: inherit; }

        /* ── Topbar ─────────────────────────────────── */
        .docs-topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; gap: 16px;
            padding: 14px 24px;
            background: color-mix(in oklch, var(--bg-card) 88%, transparent);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-faint);
        }
        .docs-brand { display: flex; align-items: baseline; gap: 7px; text-decoration: none; }
        .docs-topbar-sep { color: var(--border); }
        .docs-topbar-title { font-size: 14px; font-weight: 700; color: var(--text-muted); }
        .docs-topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 18px; }
        .docs-topbar-link { font-size: 13px; font-weight: 700; color: var(--text-muted); text-decoration: none; }
        .docs-topbar-link:hover { color: var(--green-cta); }
        .docs-topbar-cta {
            padding: 8px 16px; border-radius: 10px;
            background: var(--green-cta); color: #fff;
            font-size: 13px; font-weight: 700; text-decoration: none;
        }
        .docs-topbar-cta:hover { background: var(--green); }

        /* ── Shell ──────────────────────────────────── */
        .docs-shell { display: flex; max-width: 1180px; margin: 0 auto; }

        .docs-aside {
            width: 260px; flex-shrink: 0;
            padding: 32px 12px 64px 24px;
            position: sticky; top: 61px; align-self: flex-start;
            max-height: calc(100vh - 61px); overflow-y: auto;
        }
        .aside-label {
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.12em; color: var(--text-dim);
            padding: 0 12px 8px;
        }
        .aside-link {
            display: block; padding: 8px 12px; margin-bottom: 2px;
            border-radius: 8px; text-decoration: none;
            font-size: 13.5px; font-weight: 600; color: var(--text-muted);
            line-height: 1.4;
        }
        .aside-link:hover { background: var(--bg-surface); color: var(--text); }
        .aside-link.is-active { background: var(--green-faint); color: var(--green-cta); font-weight: 700; }
        .aside-group { margin-bottom: 26px; }

        .docs-main { flex: 1; min-width: 0; padding: 40px 24px 96px; }
        .docs-body { max-width: 720px; }

        /* ── Prosa ──────────────────────────────────── */
        .prose h1 { font-size: 34px; font-weight: 800; letter-spacing: -0.03em; margin: 0 0 14px; line-height: 1.15; }
        .prose h2 { font-size: 21px; font-weight: 700; letter-spacing: -0.02em; margin: 40px 0 12px; padding-top: 8px; }
        .prose h3 { font-size: 16px; font-weight: 700; margin: 28px 0 8px; }
        .prose p { font-size: 15px; line-height: 1.75; color: var(--text-muted); margin: 0 0 16px; }
        .prose strong { color: var(--text); font-weight: 700; }
        .prose ul, .prose ol { padding-left: 22px; margin: 0 0 18px; }
        .prose li { font-size: 15px; line-height: 1.7; color: var(--text-muted); margin-bottom: 7px; }
        .prose a { color: var(--green-cta); font-weight: 600; text-decoration: none; border-bottom: 1px solid color-mix(in oklch, var(--green-cta) 30%, transparent); }
        .prose a:hover { border-bottom-color: var(--green-cta); }
        .prose code {
            font-family: 'JetBrains Mono', monospace; font-size: 12.5px;
            background: var(--bg-surface); padding: 2px 6px; border-radius: 5px;
            color: var(--text);
        }
        .prose pre {
            background: oklch(0.21 0.014 160); color: oklch(0.93 0.01 150);
            padding: 16px 18px; border-radius: 12px; overflow-x: auto;
            margin: 0 0 20px; font-size: 12.5px; line-height: 1.65;
        }
        .prose pre code { background: none; padding: 0; color: inherit; font-size: inherit; }
        .prose blockquote {
            margin: 0 0 20px; padding: 14px 18px;
            background: var(--green-faint); border-left: 3px solid var(--green);
            border-radius: 0 10px 10px 0;
        }
        .prose blockquote p { margin: 0; color: var(--text); font-size: 14.5px; }
        .prose table { width: 100%; border-collapse: collapse; margin: 0 0 22px; font-size: 14px; display: block; overflow-x: auto; }
        .prose th {
            text-align: left; padding: 9px 12px; background: var(--bg-surface);
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-dim);
            border-bottom: 1px solid var(--border);
        }
        .prose td { padding: 11px 12px; border-bottom: 1px solid var(--border-faint); color: var(--text-muted); line-height: 1.6; }
        .prose hr { border: 0; border-top: 1px solid var(--border-faint); margin: 36px 0; }

        /* ── Navigasi bawah ─────────────────────────── */
        .docs-nextprev { display: flex; gap: 12px; margin-top: 56px; padding-top: 28px; border-top: 1px solid var(--border-faint); }
        .nextprev-card {
            flex: 1; padding: 14px 18px; border-radius: 12px;
            border: 1px solid var(--border); background: var(--bg-card);
            text-decoration: none; display: block;
        }
        .nextprev-card:hover { border-color: var(--green); }
        .nextprev-label { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-dim); }
        .nextprev-title { font-size: 14.5px; font-weight: 700; margin-top: 3px; }

        /* ── Mobile ─────────────────────────────────── */
        .docs-aside-btn { display: none; }

        @media (max-width: 900px) {
            .docs-aside { display: none; }
            .docs-aside.is-open {
                display: block; position: static; width: auto;
                max-height: none; padding: 8px 8px 20px;
                border-bottom: 1px solid var(--border-faint);
            }
            .docs-aside-btn {
                display: block; width: 100%; margin-bottom: 20px;
                padding: 11px 16px; border-radius: 10px;
                border: 1px solid var(--border); background: var(--bg-card);
                font-family: inherit; font-size: 13.5px; font-weight: 700;
                color: var(--text-muted); text-align: left; cursor: pointer;
            }
            .docs-shell { flex-direction: column; }
            .docs-main { padding: 24px 20px 72px; }
            .prose h1 { font-size: 27px; }
            .docs-nextprev { flex-direction: column; }
            .docs-topbar-link.is-hideable { display: none; }
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{ aside: false }">

    <header class="docs-topbar">
        <a href="{{ route('landing') }}" class="docs-brand">
            @include('public.partials.wordmark', ['size' => 'sm'])
        </a>
        <span class="docs-topbar-sep">/</span>
        <a href="{{ route('docs.index') }}" class="docs-topbar-title" style="text-decoration:none;">Dokumentasi</a>

        <div class="docs-topbar-actions">
            <a href="{{ route('api-docs') }}" class="docs-topbar-link is-hideable">Referensi API</a>
            <a href="{{ route('login') }}" class="docs-topbar-link is-hideable">Masuk</a>
            <a href="{{ route('register') }}" class="docs-topbar-cta">Coba Gratis</a>
        </div>
    </header>

    @yield('body')

</body>
</html>
