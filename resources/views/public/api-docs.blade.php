<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Reference — SAPI Mobile POS</title>
    <meta name="description" content="Dokumentasi lengkap REST API Mobile POS SAPI. Referensi endpoint, autentikasi Sanctum, request/response JSON.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css'])

    <style>
        /* ─── PALETTE (same as landing) ─────────────────── */
        :root {
            --bg:            oklch(0.97 0.006 150);
            --bg-card:       oklch(0.993 0.004 150);
            --bg-surface:    oklch(0.94 0.008 150);
            --green:         oklch(0.58 0.128 162);
            --green-cta:     oklch(0.51 0.12 162);
            --green-hover:   oklch(0.46 0.115 162);
            --green-faint:   oklch(0.93 0.035 162);
            --text:          oklch(0.23 0.015 160);
            --text-muted:    oklch(0.48 0.012 160);
            --text-dim:      oklch(0.62 0.010 155);
            --border:        oklch(0.88 0.010 150);
            --border-faint:  oklch(0.92 0.008 150);
            --red-soft:      oklch(0.58 0.18 27);
            /* method badge colors */
            --badge-get:     oklch(0.93 0.06 162);
            --badge-get-text:oklch(0.38 0.10 162);
            --badge-post:    oklch(0.92 0.05 250);
            --badge-post-text:oklch(0.35 0.12 250);
            --badge-patch:   oklch(0.93 0.07 70);
            --badge-patch-text:oklch(0.40 0.12 65);
            --badge-delete:  oklch(0.93 0.06 27);
            --badge-delete-text: var(--red-soft);
            /* code block */
            --code-bg:       oklch(0.17 0.01 155);
            --code-text:     oklch(0.89 0.02 155);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 1rem;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ─── LAYOUT ───────────────────────────────────── */
        .container {
            max-width: 1248px;
            margin: 0 auto;
            padding: 0 24px;
        }
        @media (min-width: 768px) { .container { padding: 0 40px; } }
        @media (min-width: 1280px) { .container { padding: 0 48px; } }

        /* ─── NAVBAR ────────────────────────────────────── */
        .nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: oklch(0.97 0.006 150 / 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-faint);
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            gap: 32px;
        }
        .nav-wordmark {
            font-size: 1.375rem;
            font-weight: 800;
            color: var(--green);
            text-decoration: none;
            letter-spacing: -0.02em;
            flex-shrink: 0;
        }
        .nav-links {
            display: none;
            list-style: none;
            gap: 4px;
            align-items: center;
        }
        @media (min-width: 768px) { .nav-links { display: flex; } }
        .nav-links a {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: color 120ms, background 120ms;
        }
        .nav-links a:hover { color: var(--text); background: var(--bg-surface); }
        .nav-actions { display: flex; align-items: center; gap: 12px; }
        .nav-back {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 120ms;
        }
        .nav-back:hover { color: var(--text); }
        .nav-badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--green-faint);
            color: var(--green-cta);
            letter-spacing: 0.02em;
        }

        /* ─── DOCS PAGE LAYOUT ──────────────────────────── */
        .docs-page {
            display: flex;
            min-height: calc(100vh - 60px);
        }

        /* ─── SIDEBAR ───────────────────────────────────── */
        .docs-sidebar {
            display: none;
            width: 256px;
            flex-shrink: 0;
            border-right: 1px solid var(--border-faint);
            background: var(--bg-card);
        }
        @media (min-width: 900px) { .docs-sidebar { display: block; } }
        .sidebar-inner {
            position: sticky;
            top: 60px;
            max-height: calc(100vh - 60px);
            overflow-y: auto;
            padding: 28px 0 40px;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        .sidebar-group {
            margin-bottom: 8px;
        }
        .sidebar-group-label {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-dim);
            padding: 6px 20px 4px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 20px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 100ms, background 100ms;
            border-left: 2px solid transparent;
        }
        .sidebar-link:hover {
            color: var(--text);
            background: var(--bg-surface);
            border-left-color: var(--border);
        }
        .sidebar-link.active {
            color: var(--green-cta);
            background: var(--green-faint);
            border-left-color: var(--green);
        }
        .sidebar-method {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 2px 6px;
            border-radius: 4px;
            flex-shrink: 0;
        }
        .sm-get  { background: var(--badge-get);    color: var(--badge-get-text); }
        .sm-post { background: var(--badge-post);   color: var(--badge-post-text); }
        .sm-patch{ background: var(--badge-patch);  color: var(--badge-patch-text); }

        /* ─── MAIN CONTENT ──────────────────────────────── */
        .docs-main {
            flex: 1;
            min-width: 0;
            padding: 48px 0 80px;
        }
        .docs-content {
            max-width: 820px;
            padding: 0 32px;
        }
        @media (min-width: 900px) { .docs-content { padding: 0 48px; } }

        /* ─── MOBILE SIDEBAR TOGGLE ─────────────────────── */
        .mobile-sidebar-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 14px;
            cursor: pointer;
            margin-bottom: 24px;
            transition: color 100ms, background 100ms;
        }
        .mobile-sidebar-btn:hover { color: var(--text); background: var(--bg-surface); }
        @media (min-width: 900px) { .mobile-sidebar-btn { display: none; } }

        .mobile-sidebar-drawer {
            background: var(--bg-card);
            border: 1px solid var(--border-faint);
            border-radius: 12px;
            margin-bottom: 28px;
            overflow: hidden;
        }
        @media (min-width: 900px) { .mobile-sidebar-drawer { display: none !important; } }

        /* ─── INTRO BLOCK ───────────────────────────────── */
        .docs-hero {
            margin-bottom: 48px;
            padding-bottom: 40px;
            border-bottom: 1px solid var(--border-faint);
        }
        .docs-hero-tag {
            display: inline-block;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 14px;
        }
        .docs-title {
            font-size: clamp(1.75rem, 3vw + 0.5rem, 2.5rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.01em;
            color: var(--text);
            margin-bottom: 14px;
        }
        .docs-subtitle {
            font-size: 1.0625rem;
            color: var(--text-muted);
            max-width: 58ch;
            line-height: 1.65;
            margin-bottom: 24px;
        }
        .base-url-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--code-bg);
            color: var(--code-text);
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.875rem;
            padding: 10px 16px;
            border-radius: 8px;
        }
        .base-url-pill span {
            color: oklch(0.72 0.12 162);
            font-weight: 500;
        }

        /* ─── SECTION HEADINGS ──────────────────────────── */
        .docs-section {
            margin-bottom: 56px;
            scroll-margin-top: 80px;
        }
        .section-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-faint);
        }
        .section-desc {
            font-size: 0.9375rem;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 20px;
        }

        /* ─── INFO CARDS ────────────────────────────────── */
        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-faint);
            border-radius: 10px;
            padding: 20px 22px;
            margin-bottom: 16px;
        }
        .info-card-title {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 8px;
        }
        .info-card p { font-size: 0.9rem; color: var(--text-muted); line-height: 1.65; }
        .info-card code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.825rem;
            background: var(--bg-surface);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text);
        }

        /* ─── STATUS CODES TABLE ────────────────────────── */
        .status-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0;
            border: 1px solid var(--border-faint);
            border-radius: 10px;
            overflow: hidden;
            font-size: 0.875rem;
        }
        .status-grid > div {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-faint);
        }
        .status-grid > div:last-child,
        .status-grid > div:nth-last-child(2) { border-bottom: none; }
        .status-code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-weight: 600;
            color: var(--text);
            background: var(--bg-surface);
            border-right: 1px solid var(--border-faint);
            white-space: nowrap;
        }
        .status-desc { color: var(--text-muted); }

        /* ─── ENDPOINT CARD ─────────────────────────────── */
        .endpoint-card {
            background: var(--bg-card);
            border: 1px solid var(--border-faint);
            border-radius: 12px;
            margin-bottom: 28px;
            overflow: hidden;
            scroll-margin-top: 80px;
        }
        .endpoint-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border-faint);
            flex-wrap: wrap;
        }
        .method-badge {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 5px;
            flex-shrink: 0;
            margin-top: 3px;
        }
        .mb-get    { background: var(--badge-get);    color: var(--badge-get-text); }
        .mb-post   { background: var(--badge-post);   color: var(--badge-post-text); }
        .mb-patch  { background: var(--badge-patch);  color: var(--badge-patch-text); }
        .mb-delete { background: var(--badge-delete); color: var(--badge-delete-text); }

        .endpoint-path {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--text);
            flex: 1;
            word-break: break-all;
        }
        .endpoint-path em {
            font-style: normal;
            color: var(--badge-patch-text);
        }
        .endpoint-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .role-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 20px;
        }
        .role-public  { background: var(--bg-surface); color: var(--text-dim); }
        .role-auth    { background: oklch(0.92 0.05 250); color: oklch(0.35 0.12 250); }
        .role-cashier { background: var(--badge-patch); color: var(--badge-patch-text); }
        .role-owner   { background: var(--badge-delete); color: var(--badge-delete-text); }
        .rate-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 20px;
            background: var(--green-faint);
            color: var(--green-cta);
        }

        .endpoint-body { padding: 20px 24px; }
        .endpoint-desc {
            font-size: 0.9375rem;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.65;
        }

        /* ─── PARAM TABLE ───────────────────────────────── */
        .param-section { margin-bottom: 22px; }
        .param-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 8px;
        }
        .param-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8375rem;
            border: 1px solid var(--border-faint);
            border-radius: 8px;
            overflow: hidden;
        }
        .param-table th {
            background: var(--bg-surface);
            text-align: left;
            padding: 8px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--text-dim);
            border-bottom: 1px solid var(--border-faint);
        }
        .param-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-faint);
            color: var(--text-muted);
            vertical-align: top;
            line-height: 1.5;
        }
        .param-table tr:last-child td { border-bottom: none; }
        .param-table code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.8rem;
            color: var(--text);
            background: var(--bg-surface);
            padding: 1px 5px;
            border-radius: 4px;
        }
        .param-name {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.8rem;
            color: var(--text);
            white-space: nowrap;
        }
        .param-required {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--red-soft);
            letter-spacing: 0.04em;
        }
        .param-optional {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-dim);
        }

        /* ─── CODE BLOCK ────────────────────────────────── */
        .code-block-wrap {
            position: relative;
            margin-bottom: 22px;
        }
        .code-block-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 6px;
        }
        .code-block {
            background: var(--code-bg);
            border-radius: 8px;
            padding: 18px 20px;
            overflow-x: auto;
            position: relative;
        }
        .code-block pre {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.8125rem;
            line-height: 1.65;
            color: var(--code-text);
            white-space: pre;
            margin: 0;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.72rem;
            font-weight: 600;
            color: oklch(0.65 0.02 155);
            background: oklch(0.22 0.01 155);
            border: 1px solid oklch(0.28 0.01 155);
            border-radius: 5px;
            padding: 5px 10px;
            cursor: pointer;
            transition: color 100ms, background 100ms;
        }
        .copy-btn:hover { color: var(--code-text); background: oklch(0.27 0.01 155); }
        .copy-btn.copied { color: oklch(0.72 0.12 162); }

        /* syntax highlighting helpers */
        .tok-key  { color: oklch(0.72 0.12 162); }     /* json key */
        .tok-str  { color: oklch(0.78 0.09 220); }     /* string value */
        .tok-num  { color: oklch(0.78 0.10 60); }      /* number */
        .tok-bool { color: oklch(0.75 0.10 27); }      /* bool/null */
        .tok-comment { color: oklch(0.50 0.02 155); font-style: italic; }
        .tok-curl-flag { color: oklch(0.72 0.10 60); }
        .tok-curl-url  { color: oklch(0.78 0.09 220); }

        /* ─── FOOTER ────────────────────────────────────── */
        .docs-footer {
            border-top: 1px solid var(--border-faint);
            padding: 32px 0;
            margin-top: 20px;
        }
        .docs-footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .footer-brand { font-weight: 700; color: var(--green); font-size: 0.9rem; }
        .footer-copy { font-size: 0.8rem; color: var(--text-dim); }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body x-data="{ mobileSidebar: false }">

    <!-- ─── NAV ───────────────────────────────────────── -->
    <nav class="nav">
        <div class="container">
            <div class="nav-inner">
                <a href="/" class="nav-wordmark">SAPI</a>
                <ul class="nav-links">
                    <li><a href="/">Beranda</a></li>
                    <li><a href="/#fitur">Fitur</a></li>
                    <li><a href="/#harga">Harga</a></li>
                </ul>
                <div class="nav-actions">
                    <span class="nav-badge">API v1</span>
                    <a href="/" class="nav-back">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M9 11L5 7l4-4"/>
                        </svg>
                        Beranda
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ─── PAGE ──────────────────────────────────────── -->
    <div class="docs-page">

        <!-- SIDEBAR (desktop) -->
        <aside class="docs-sidebar">
            <div class="sidebar-inner">

                <div class="sidebar-group">
                    <div class="sidebar-group-label">Mulai</div>
                    <a href="#intro" class="sidebar-link">Pengantar</a>
                    <a href="#autentikasi" class="sidebar-link">Autentikasi</a>
                    <a href="#konvensi" class="sidebar-link">Konvensi & Error</a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">Auth</div>
                    <a href="#ep-login" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Login
                    </a>
                    <a href="#ep-logout" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Logout
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">Tenant & Produk</div>
                    <a href="#ep-tenant-profile" class="sidebar-link">
                        <span class="sidebar-method sm-get">GET</span>
                        Profil Tenant
                    </a>
                    <a href="#ep-products" class="sidebar-link">
                        <span class="sidebar-method sm-get">GET</span>
                        Daftar Produk
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">Cash Drawer</div>
                    <a href="#ep-drawer-status" class="sidebar-link">
                        <span class="sidebar-method sm-get">GET</span>
                        Status Kas
                    </a>
                    <a href="#ep-drawer-open" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Buka Kas
                    </a>
                    <a href="#ep-drawer-close" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Tutup Kas
                    </a>
                    <a href="#ep-drawer-summary" class="sidebar-link">
                        <span class="sidebar-method sm-get">GET</span>
                        Ringkasan Kas
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">Transaksi</div>
                    <a href="#ep-trx-create" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Buat Transaksi
                    </a>
                    <a href="#ep-trx-list" class="sidebar-link">
                        <span class="sidebar-method sm-get">GET</span>
                        Daftar Transaksi
                    </a>
                    <a href="#ep-trx-pay" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Bayar Open Bill
                    </a>
                    <a href="#ep-trx-receipt" class="sidebar-link">
                        <span class="sidebar-method sm-get">GET</span>
                        Struk
                    </a>
                    <a href="#ep-trx-void" class="sidebar-link">
                        <span class="sidebar-method sm-post">POST</span>
                        Void Transaksi
                    </a>
                </div>

            </div>
        </aside>

        <!-- MAIN -->
        <main class="docs-main">
            <div class="docs-content">

                <!-- Mobile sidebar toggle -->
                <button class="mobile-sidebar-btn" @click="mobileSidebar = !mobileSidebar">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="1" y="3" width="13" height="1.5" rx="0.75" fill="currentColor" stroke="none"/>
                        <rect x="1" y="7" width="9" height="1.5" rx="0.75" fill="currentColor" stroke="none"/>
                        <rect x="1" y="11" width="11" height="1.5" rx="0.75" fill="currentColor" stroke="none"/>
                    </svg>
                    <span x-text="mobileSidebar ? 'Tutup Navigasi' : 'Navigasi Endpoint'">Navigasi Endpoint</span>
                </button>

                <!-- Mobile sidebar drawer -->
                <div class="mobile-sidebar-drawer" x-show="mobileSidebar" x-cloak
                    x-transition:enter="transition duration-150 ease-out"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition duration-100 ease-in"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1">
                    <div style="padding: 12px 0;">
                        <div class="sidebar-group-label" style="padding: 8px 20px 4px;">Mulai</div>
                        <a href="#intro" class="sidebar-link" @click="mobileSidebar=false">Pengantar</a>
                        <a href="#autentikasi" class="sidebar-link" @click="mobileSidebar=false">Autentikasi</a>
                        <a href="#konvensi" class="sidebar-link" @click="mobileSidebar=false">Konvensi & Error</a>
                        <div class="sidebar-group-label" style="padding: 12px 20px 4px;">Auth</div>
                        <a href="#ep-login" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Login</a>
                        <a href="#ep-logout" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Logout</a>
                        <div class="sidebar-group-label" style="padding: 12px 20px 4px;">Tenant & Produk</div>
                        <a href="#ep-tenant-profile" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-get">GET</span> Profil Tenant</a>
                        <a href="#ep-products" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-get">GET</span> Daftar Produk</a>
                        <div class="sidebar-group-label" style="padding: 12px 20px 4px;">Cash Drawer</div>
                        <a href="#ep-drawer-status" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-get">GET</span> Status Kas</a>
                        <a href="#ep-drawer-open" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Buka Kas</a>
                        <a href="#ep-drawer-close" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Tutup Kas</a>
                        <a href="#ep-drawer-summary" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-get">GET</span> Ringkasan Kas</a>
                        <div class="sidebar-group-label" style="padding: 12px 20px 4px;">Transaksi</div>
                        <a href="#ep-trx-create" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Buat Transaksi</a>
                        <a href="#ep-trx-list" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-get">GET</span> Daftar Transaksi</a>
                        <a href="#ep-trx-pay" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Bayar Open Bill</a>
                        <a href="#ep-trx-receipt" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-get">GET</span> Struk</a>
                        <a href="#ep-trx-void" class="sidebar-link" @click="mobileSidebar=false"><span class="sidebar-method sm-post">POST</span> Void Transaksi</a>
                    </div>
                </div>

                <!-- ─── INTRO ─────────────────────────────── -->
                <section class="docs-section docs-hero" id="intro">
                    <span class="docs-hero-tag">REST API · Mobile POS</span>
                    <h1 class="docs-title">API Reference — SAPI Mobile POS</h1>
                    <p class="docs-subtitle">
                        Referensi lengkap endpoint REST API untuk aplikasi kasir mobile SAPI.
                        Semua endpoint mengembalikan JSON dan menggunakan autentikasi Bearer token via Laravel Sanctum.
                    </p>
                    <div class="base-url-pill">
                        <span>BASE URL</span>
                        https://yourdomain.com
                    </div>
                    <div class="base-url-pill" style="margin-top:8px;">
                        <span>API VERSION</span>
                        v1 &mdash; semua endpoint berada di bawah <code>/api/v1/</code>
                    </div>
                </section>

                <!-- ─── AUTENTIKASI ───────────────────────── -->
                <section class="docs-section" id="autentikasi">
                    <h2 class="section-title">Autentikasi</h2>
                    <p class="section-desc">
                        API menggunakan <strong>Laravel Sanctum Bearer Token</strong>. Login melalui
                        <code style="font-family:'JetBrains Mono',monospace;font-size:0.85em;">POST /api/v1/mobile/login</code>
                        untuk mendapatkan token, lalu sertakan di setiap request terproteksi.
                    </p>

                    <div class="info-card">
                        <div class="info-card-title">Header wajib untuk endpoint terproteksi</div>
                        <p style="margin-top:4px;">
                            <code>Authorization: Bearer {token}</code><br>
                            <code>Accept: application/json</code><br>
                            <code>Content-Type: application/json</code>
                        </p>
                    </div>

                    <div class="info-card">
                        <div class="info-card-title">Multi-tenant & Role</div>
                        <p>
                            Setiap pengguna terikat pada satu <strong>tenant</strong>. Semua data difilter otomatis
                            berdasarkan tenant user yang login — tidak perlu mengirim <code>tenant_id</code> secara eksplisit.<br><br>
                            Role yang tersedia: <code>owner</code> dan <code>cashier</code>.
                            Beberapa endpoint hanya bisa diakses oleh role tertentu (ditandai dengan badge pada tiap endpoint).
                        </p>
                    </div>

                    <div class="info-card">
                        <div class="info-card-title">One device, one token</div>
                        <p>Setiap login baru akan menghapus token lama untuk akun yang sama. Simpan token dengan aman di sisi client.</p>
                    </div>
                </section>

                <!-- ─── KONVENSI ──────────────────────────── -->
                <section class="docs-section" id="konvensi">
                    <h2 class="section-title">Konvensi & Kode Status</h2>
                    <p class="section-desc">Semua request dan response menggunakan format JSON. Tambahkan header <code style="font-family:'JetBrains Mono',monospace;font-size:0.85em;">Accept: application/json</code> agar error juga dikembalikan sebagai JSON.</p>

                    <div class="status-grid" style="margin-bottom:20px;">
                        <div class="status-code">200</div><div class="status-desc">OK — request berhasil</div>
                        <div class="status-code">201</div><div class="status-desc">Created — resource berhasil dibuat</div>
                        <div class="status-code">401</div><div class="status-desc">Unauthorized — token tidak valid atau tidak dikirim</div>
                        <div class="status-code">403</div><div class="status-desc">Forbidden — role tidak cukup atau bukan milik tenant ini</div>
                        <div class="status-code">422</div><div class="status-desc">Unprocessable — validasi gagal atau error bisnis</div>
                        <div class="status-code">429</div><div class="status-desc">Too Many Requests — rate limit terlampaui</div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-title">Bentuk response error (422)</div>
                        <div class="code-block" style="margin-top:8px;">
                            <pre>{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Email atau password salah."</span>
}</pre>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-title">Rate Limit</div>
                        <p>
                            <code>POST /api/v1/mobile/login</code> — maksimum <strong>5 request per menit</strong> per IP.<br>
                            Endpoint lain tidak memiliki rate limit khusus.
                        </p>
                    </div>
                </section>

                <!-- ════════════════════════════════════════ -->
                <!-- AUTH ENDPOINTS                           -->
                <!-- ════════════════════════════════════════ -->

                <section class="docs-section">
                    <h2 class="section-title">Auth</h2>
                </section>

                <!-- LOGIN -->
                <div class="endpoint-card" id="ep-login">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/login</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-public">Publik</span>
                                <span class="rate-badge">5/menit</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Login kasir atau owner. Menghapus token lama dan mengembalikan token Sanctum baru beserta informasi user dan tenant.</p>

                        <div class="param-section">
                            <div class="param-label">Request Body</div>
                            <table class="param-table">
                                <thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">email</span> <span class="param-required">WAJIB</span></td><td><code>string</code></td><td>Alamat email terdaftar</td></tr>
                                    <tr><td><span class="param-name">password</span> <span class="param-required">WAJIB</span></td><td><code>string</code></td><td>Password pengguna</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request (cURL)</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-curl-flag">curl</span> -X POST https://yourdomain.com/api/v1/mobile/login \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Accept: application/json"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Content-Type: application/json"</span> \
  <span class="tok-curl-flag">-d</span> <span class="tok-str">'{
    "email": "kasir@cafesapi.com",
    "password": "rahasia123"
  }'</span></pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"token"</span>: <span class="tok-str">"1|abc123xyz..."</span>,
  <span class="tok-key">"user"</span>: {
    <span class="tok-key">"id"</span>: <span class="tok-num">5</span>,
    <span class="tok-key">"name"</span>: <span class="tok-str">"Budi Santoso"</span>,
    <span class="tok-key">"email"</span>: <span class="tok-str">"kasir@cafesapi.com"</span>,
    <span class="tok-key">"role"</span>: <span class="tok-str">"cashier"</span>
  },
  <span class="tok-key">"tenant"</span>: {
    <span class="tok-key">"id"</span>: <span class="tok-num">1</span>,
    <span class="tok-key">"name"</span>: <span class="tok-str">"SAPI Cafe"</span>,
    <span class="tok-key">"address"</span>: <span class="tok-str">"Jl. Sudirman No. 12"</span>,
    <span class="tok-key">"phone"</span>: <span class="tok-str">"+62812345678"</span>
  }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LOGOUT -->
                <div class="endpoint-card" id="ep-logout">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/logout</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-auth">Bearer Token</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mencabut token saat ini. Gunakan saat pengguna keluar dari aplikasi.</p>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request (cURL)</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-curl-flag">curl</span> -X POST https://yourdomain.com/api/v1/mobile/logout \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Authorization: Bearer 1|abc123xyz..."</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Accept: application/json"</span></pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Logout berhasil."</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════ -->
                <!-- TENANT & PRODUK                          -->
                <!-- ════════════════════════════════════════ -->

                <section class="docs-section">
                    <h2 class="section-title">Tenant & Produk</h2>
                </section>

                <!-- TENANT PROFILE -->
                <div class="endpoint-card" id="ep-tenant-profile">
                    <div class="endpoint-header">
                        <span class="method-badge mb-get">GET</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/tenant/profile</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-auth">Bearer Token</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mengembalikan informasi tenant milik pengguna yang sedang login (nama, alamat, telepon).</p>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request (cURL)</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-curl-flag">curl</span> https://yourdomain.com/api/v1/mobile/tenant/profile \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Authorization: Bearer {token}"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Accept: application/json"</span></pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"data"</span>: {
    <span class="tok-key">"id"</span>: <span class="tok-num">1</span>,
    <span class="tok-key">"name"</span>: <span class="tok-str">"SAPI Cafe"</span>,
    <span class="tok-key">"address"</span>: <span class="tok-str">"Jl. Sudirman No. 12"</span>,
    <span class="tok-key">"phone"</span>: <span class="tok-str">"+62812345678"</span>
  }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRODUCTS -->
                <div class="endpoint-card" id="ep-products">
                    <div class="endpoint-header">
                        <span class="method-badge mb-get">GET</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/products</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-auth">Bearer Token</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mengembalikan semua produk aktif beserta varian yang memiliki stok > 0 dan kategorinya. Digunakan untuk membangun daftar menu di kasir.</p>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"data"</span>: [
    {
      <span class="tok-key">"id"</span>: <span class="tok-num">1</span>,
      <span class="tok-key">"name"</span>: <span class="tok-str">"Kopi Susu"</span>,
      <span class="tok-key">"category_id"</span>: <span class="tok-num">2</span>,
      <span class="tok-key">"category"</span>: { <span class="tok-key">"id"</span>: <span class="tok-num">2</span>, <span class="tok-key">"name"</span>: <span class="tok-str">"Minuman"</span> },
      <span class="tok-key">"variants"</span>: [
        {
          <span class="tok-key">"id"</span>: <span class="tok-num">10</span>,
          <span class="tok-key">"product_id"</span>: <span class="tok-num">1</span>,
          <span class="tok-key">"name"</span>: <span class="tok-str">"Regular"</span>,
          <span class="tok-key">"price"</span>: <span class="tok-num">18000</span>,
          <span class="tok-key">"stock"</span>: <span class="tok-num">50</span>
        }
      ]
    }
  ]
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════ -->
                <!-- CASH DRAWER                              -->
                <!-- ════════════════════════════════════════ -->

                <section class="docs-section">
                    <h2 class="section-title">Cash Drawer</h2>
                </section>

                <!-- DRAWER STATUS -->
                <div class="endpoint-card" id="ep-drawer-status">
                    <div class="endpoint-header">
                        <span class="method-badge mb-get">GET</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/cash-drawer/status</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-auth">Bearer Token</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mengecek apakah pengguna saat ini memiliki sesi kas yang sedang terbuka.</p>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-comment">// Sesi kas terbuka</span>
{
  <span class="tok-key">"is_open"</span>: <span class="tok-bool">true</span>,
  <span class="tok-key">"drawer_id"</span>: <span class="tok-num">7</span>,
  <span class="tok-key">"opened_at"</span>: <span class="tok-str">"2026-05-29T08:00:00.000000Z"</span>
}

<span class="tok-comment">// Tidak ada sesi terbuka</span>
{
  <span class="tok-key">"is_open"</span>: <span class="tok-bool">false</span>,
  <span class="tok-key">"drawer_id"</span>: <span class="tok-bool">null</span>,
  <span class="tok-key">"opened_at"</span>: <span class="tok-bool">null</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DRAWER OPEN -->
                <div class="endpoint-card" id="ep-drawer-open">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/cash-drawer/open</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-cashier">Cashier / Owner</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Membuka sesi kas baru dengan modal awal. Gagal jika sudah ada sesi kas yang terbuka untuk user ini.</p>

                        <div class="param-section">
                            <div class="param-label">Request Body</div>
                            <table class="param-table">
                                <thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">opening_amount</span> <span class="param-required">WAJIB</span></td><td><code>numeric</code></td><td>Modal awal kas (min 0)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request (cURL)</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-curl-flag">curl</span> -X POST https://yourdomain.com/api/v1/mobile/cash-drawer/open \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Authorization: Bearer {token}"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Accept: application/json"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Content-Type: application/json"</span> \
  <span class="tok-curl-flag">-d</span> <span class="tok-str">'{ "opening_amount": 500000 }'</span></pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 201 Created</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Kas berhasil dibuka."</span>,
  <span class="tok-key">"drawer_id"</span>: <span class="tok-num">7</span>,
  <span class="tok-key">"opened_at"</span>: <span class="tok-str">"2026-05-29T08:00:00.000000Z"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DRAWER CLOSE -->
                <div class="endpoint-card" id="ep-drawer-close">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/cash-drawer/close</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-cashier">Cashier / Owner</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Menutup sesi kas aktif. Sistem otomatis menghitung selisih antara kas yang diharapkan (berdasarkan transaksi) dan kas aktual yang dihitung pengguna.</p>

                        <div class="param-section">
                            <div class="param-label">Request Body</div>
                            <table class="param-table">
                                <thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">closing_amount</span> <span class="param-required">WAJIB</span></td><td><code>numeric</code></td><td>Jumlah kas fisik yang dihitung saat tutup (min 0)</td></tr>
                                    <tr><td><span class="param-name">notes</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Catatan penutupan kas (maks 500 karakter)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Kas berhasil ditutup."</span>,
  <span class="tok-key">"drawer_id"</span>: <span class="tok-num">7</span>,
  <span class="tok-key">"expected_amount"</span>: <span class="tok-num">515000</span>,
  <span class="tok-key">"closing_amount"</span>: <span class="tok-num">520000</span>,
  <span class="tok-key">"difference"</span>: <span class="tok-num">5000</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DRAWER SUMMARY -->
                <div class="endpoint-card" id="ep-drawer-summary">
                    <div class="endpoint-header">
                        <span class="method-badge mb-get">GET</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/cash-drawer/<em>{cashDrawer}</em>/summary</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-cashier">Cashier / Owner</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mengembalikan ringkasan sesi kas: detail buka/tutup, jumlah transaksi, dan rincian per metode pembayaran. Kasir hanya bisa melihat sesi kasnya sendiri; owner bisa melihat sesi siapapun dalam tenant yang sama.</p>

                        <div class="param-section">
                            <div class="param-label">Path Parameter</div>
                            <table class="param-table">
                                <thead><tr><th>Parameter</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">cashDrawer</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>ID sesi kas (<code>drawer_id</code> dari endpoint buka/tutup)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"data"</span>: {
    <span class="tok-key">"drawer"</span>: {
      <span class="tok-key">"id"</span>: <span class="tok-num">7</span>,
      <span class="tok-key">"opening_amount"</span>: <span class="tok-num">500000</span>,
      <span class="tok-key">"closing_amount"</span>: <span class="tok-num">520000</span>,
      <span class="tok-key">"expected_amount"</span>: <span class="tok-num">515000</span>,
      <span class="tok-key">"difference"</span>: <span class="tok-num">5000</span>,
      <span class="tok-key">"notes"</span>: <span class="tok-str">"Sesuai"</span>,
      <span class="tok-key">"opened_at"</span>: <span class="tok-str">"2026-05-29T08:00:00.000000Z"</span>,
      <span class="tok-key">"closed_at"</span>: <span class="tok-str">"2026-05-29T17:00:00.000000Z"</span>,
      <span class="tok-key">"is_open"</span>: <span class="tok-bool">false</span>
    },
    <span class="tok-key">"transaction_count"</span>: <span class="tok-num">45</span>,
    <span class="tok-key">"payment_summary"</span>: [
      { <span class="tok-key">"name"</span>: <span class="tok-str">"Tunai"</span>, <span class="tok-key">"type"</span>: <span class="tok-str">"cash"</span>, <span class="tok-key">"total"</span>: <span class="tok-num">300000</span> },
      { <span class="tok-key">"name"</span>: <span class="tok-str">"QRIS"</span>, <span class="tok-key">"type"</span>: <span class="tok-str">"digital"</span>, <span class="tok-key">"total"</span>: <span class="tok-num">215000</span> }
    ]
  }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════ -->
                <!-- TRANSAKSI                                -->
                <!-- ════════════════════════════════════════ -->

                <section class="docs-section">
                    <h2 class="section-title">Transaksi</h2>
                </section>

                <!-- CREATE TRANSACTION -->
                <div class="endpoint-card" id="ep-trx-create">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/transactions</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-auth">Bearer Token</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">
                            Membuat transaksi POS baru. Stok langsung dikurangi saat endpoint ini dipanggil.<br>
                            Dua mode: <strong>transaksi langsung</strong> (kirim <code>payments</code>, status <em>completed</em>) atau
                            <strong>open bill</strong> (<code>is_open_bill: true</code>, pembayaran menyusul via endpoint <em>Bayar Open Bill</em>).
                        </p>

                        <div class="param-section">
                            <div class="param-label">Request Body</div>
                            <table class="param-table">
                                <thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">items</span> <span class="param-required">WAJIB</span></td><td><code>array</code></td><td>Min 1 item</td></tr>
                                    <tr><td><span class="param-name">items[].variant_id</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>ID varian produk</td></tr>
                                    <tr><td><span class="param-name">items[].variant_name</span> <span class="param-required">WAJIB</span></td><td><code>string</code></td><td>Nama varian (disimpan di snapshot transaksi)</td></tr>
                                    <tr><td><span class="param-name">items[].qty</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>Jumlah (min 1)</td></tr>
                                    <tr><td><span class="param-name">items[].notes</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Catatan item (maks 500 karakter)</td></tr>
                                    <tr><td><span class="param-name">items[].modifiers</span> <span class="param-optional">OPSIONAL</span></td><td><code>array</code></td><td>Modifier/topping item</td></tr>
                                    <tr><td><span class="param-name">items[].modifiers[].id</span> <span class="param-required">WAJIB*</span></td><td><code>integer</code></td><td>ID modifier</td></tr>
                                    <tr><td><span class="param-name">is_open_bill</span> <span class="param-optional">OPSIONAL</span></td><td><code>boolean</code></td><td>Jika <code>true</code>, field <code>payments</code> tidak diperlukan</td></tr>
                                    <tr><td><span class="param-name">order_type</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td><code>dine_in</code> atau <code>takeaway</code></td></tr>
                                    <tr><td><span class="param-name">customer_name</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Nama pelanggan (maks 255)</td></tr>
                                    <tr><td><span class="param-name">table_number</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Nomor meja (maks 50)</td></tr>
                                    <tr><td><span class="param-name">notes</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Catatan transaksi (maks 500)</td></tr>
                                    <tr><td><span class="param-name">payments</span> <span class="param-required">WAJIB†</span></td><td><code>array</code></td><td>†Wajib jika bukan open bill</td></tr>
                                    <tr><td><span class="param-name">payments[].payment_method_id</span> <span class="param-required">WAJIB†</span></td><td><code>integer</code></td><td>ID metode pembayaran</td></tr>
                                    <tr><td><span class="param-name">payments[].amount</span> <span class="param-required">WAJIB†</span></td><td><code>numeric</code></td><td>Nominal pembayaran (min 0)</td></tr>
                                    <tr><td><span class="param-name">payments[].reference_code</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Kode referensi transfer/QRIS (maks 255)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request — Transaksi Langsung</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"items"</span>: [
    {
      <span class="tok-key">"variant_id"</span>: <span class="tok-num">10</span>,
      <span class="tok-key">"variant_name"</span>: <span class="tok-str">"Kopi Susu Regular"</span>,
      <span class="tok-key">"qty"</span>: <span class="tok-num">2</span>,
      <span class="tok-key">"notes"</span>: <span class="tok-str">"Gula sedikit"</span>,
      <span class="tok-key">"modifiers"</span>: [{ <span class="tok-key">"id"</span>: <span class="tok-num">3</span> }]
    }
  ],
  <span class="tok-key">"order_type"</span>: <span class="tok-str">"dine_in"</span>,
  <span class="tok-key">"table_number"</span>: <span class="tok-str">"A2"</span>,
  <span class="tok-key">"payments"</span>: [
    { <span class="tok-key">"payment_method_id"</span>: <span class="tok-num">1</span>, <span class="tok-key">"amount"</span>: <span class="tok-num">40000</span> }
  ]
}</pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 201 Created</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Transaksi berhasil."</span>,
  <span class="tok-key">"transaction_id"</span>: <span class="tok-num">42</span>,
  <span class="tok-key">"code"</span>: <span class="tok-str">"TRX-2026-001"</span>,
  <span class="tok-key">"total_amount"</span>: <span class="tok-num">36000</span>,
  <span class="tok-key">"change_amount"</span>: <span class="tok-num">4000</span>,
  <span class="tok-key">"status"</span>: <span class="tok-str">"completed"</span>,
  <span class="tok-key">"is_open_bill"</span>: <span class="tok-bool">false</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LIST TRANSACTIONS -->
                <div class="endpoint-card" id="ep-trx-list">
                    <div class="endpoint-header">
                        <span class="method-badge mb-get">GET</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/transactions</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-cashier">Cashier / Owner</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mengembalikan daftar transaksi dengan pagination. Kasir hanya melihat transaksinya sendiri; owner melihat semua transaksi tenant.</p>

                        <div class="param-section">
                            <div class="param-label">Query Parameters</div>
                            <table class="param-table">
                                <thead><tr><th>Parameter</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">from</span> <span class="param-optional">OPSIONAL</span></td><td><code>date</code></td><td>Filter dari tanggal (format: <code>YYYY-MM-DD</code>)</td></tr>
                                    <tr><td><span class="param-name">to</span> <span class="param-optional">OPSIONAL</span></td><td><code>date</code></td><td>Filter sampai tanggal (format: <code>YYYY-MM-DD</code>)</td></tr>
                                    <tr><td><span class="param-name">status</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td><code>pending</code>, <code>completed</code>, atau <code>voided</code></td></tr>
                                    <tr><td><span class="param-name">per_page</span> <span class="param-optional">OPSIONAL</span></td><td><code>integer</code></td><td>Jumlah item per halaman (default: 20)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request (cURL)</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-curl-flag">curl</span> <span class="tok-curl-url">"https://yourdomain.com/api/v1/mobile/transactions?from=2026-05-01&status=completed&per_page=10"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Authorization: Bearer {token}"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Accept: application/json"</span></pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"data"</span>: [
    {
      <span class="tok-key">"id"</span>: <span class="tok-num">42</span>,
      <span class="tok-key">"code"</span>: <span class="tok-str">"TRX-2026-001"</span>,
      <span class="tok-key">"cashier"</span>: <span class="tok-str">"Budi Santoso"</span>,
      <span class="tok-key">"total_amount"</span>: <span class="tok-num">36000</span>,
      <span class="tok-key">"status"</span>: <span class="tok-str">"completed"</span>,
      <span class="tok-key">"order_type"</span>: <span class="tok-str">"dine_in"</span>,
      <span class="tok-key">"customer_name"</span>: <span class="tok-bool">null</span>,
      <span class="tok-key">"table_number"</span>: <span class="tok-str">"A2"</span>,
      <span class="tok-key">"created_at"</span>: <span class="tok-str">"29/05/2026 14:30"</span>
    }
  ],
  <span class="tok-key">"meta"</span>: {
    <span class="tok-key">"current_page"</span>: <span class="tok-num">1</span>,
    <span class="tok-key">"last_page"</span>: <span class="tok-num">5</span>,
    <span class="tok-key">"per_page"</span>: <span class="tok-num">10</span>,
    <span class="tok-key">"total"</span>: <span class="tok-num">48</span>
  }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PAY OPEN BILL -->
                <div class="endpoint-card" id="ep-trx-pay">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/transactions/<em>{transaction}</em>/pay</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-cashier">Cashier / Owner</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Membayar transaksi open bill yang masih berstatus <code>pending</code>. Setelah berhasil, status transaksi berubah menjadi <code>completed</code>.</p>

                        <div class="param-section">
                            <div class="param-label">Path Parameter</div>
                            <table class="param-table">
                                <thead><tr><th>Parameter</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">transaction</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>ID transaksi</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="param-section">
                            <div class="param-label">Request Body</div>
                            <table class="param-table">
                                <thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">payments</span> <span class="param-required">WAJIB</span></td><td><code>array</code></td><td>Min 1 pembayaran</td></tr>
                                    <tr><td><span class="param-name">payments[].payment_method_id</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>ID metode pembayaran</td></tr>
                                    <tr><td><span class="param-name">payments[].amount</span> <span class="param-required">WAJIB</span></td><td><code>numeric</code></td><td>Nominal pembayaran (min 0)</td></tr>
                                    <tr><td><span class="param-name">payments[].reference_code</span> <span class="param-optional">OPSIONAL</span></td><td><code>string</code></td><td>Kode referensi (maks 255)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Open bill berhasil dibayar."</span>,
  <span class="tok-key">"transaction_id"</span>: <span class="tok-num">42</span>,
  <span class="tok-key">"code"</span>: <span class="tok-str">"TRX-2026-001"</span>,
  <span class="tok-key">"total_amount"</span>: <span class="tok-num">36000</span>,
  <span class="tok-key">"change_amount"</span>: <span class="tok-num">4000</span>,
  <span class="tok-key">"status"</span>: <span class="tok-str">"completed"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECEIPT -->
                <div class="endpoint-card" id="ep-trx-receipt">
                    <div class="endpoint-header">
                        <span class="method-badge mb-get">GET</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/transactions/<em>{transaction}</em>/receipt</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-auth">Bearer Token</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Mengembalikan data struk lengkap: informasi tenant, detail transaksi, item dengan modifier, dan rincian pembayaran. Digunakan untuk mencetak struk.</p>

                        <div class="param-section">
                            <div class="param-label">Path Parameter</div>
                            <table class="param-table">
                                <thead><tr><th>Parameter</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">transaction</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>ID transaksi</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"data"</span>: {
    <span class="tok-key">"tenant"</span>: {
      <span class="tok-key">"name"</span>: <span class="tok-str">"SAPI Cafe"</span>,
      <span class="tok-key">"address"</span>: <span class="tok-str">"Jl. Sudirman No. 12"</span>,
      <span class="tok-key">"phone"</span>: <span class="tok-str">"+62812345678"</span>
    },
    <span class="tok-key">"transaction"</span>: {
      <span class="tok-key">"code"</span>: <span class="tok-str">"TRX-2026-001"</span>,
      <span class="tok-key">"date"</span>: <span class="tok-str">"29/05/2026 14:30"</span>,
      <span class="tok-key">"cashier"</span>: <span class="tok-str">"Budi Santoso"</span>,
      <span class="tok-key">"total_amount"</span>: <span class="tok-num">36000</span>,
      <span class="tok-key">"change_amount"</span>: <span class="tok-num">4000</span>,
      <span class="tok-key">"status"</span>: <span class="tok-str">"completed"</span>,
      <span class="tok-key">"order_type"</span>: <span class="tok-str">"dine_in"</span>,
      <span class="tok-key">"table_number"</span>: <span class="tok-str">"A2"</span>,
      <span class="tok-key">"notes"</span>: <span class="tok-bool">null</span>,
      <span class="tok-key">"is_open_bill"</span>: <span class="tok-bool">false</span>
    },
    <span class="tok-key">"items"</span>: [
      {
        <span class="tok-key">"name"</span>: <span class="tok-str">"Kopi Susu Regular"</span>,
        <span class="tok-key">"qty"</span>: <span class="tok-num">2</span>,
        <span class="tok-key">"price"</span>: <span class="tok-num">18000</span>,
        <span class="tok-key">"subtotal"</span>: <span class="tok-num">36000</span>,
        <span class="tok-key">"notes"</span>: <span class="tok-str">"Gula sedikit"</span>,
        <span class="tok-key">"modifiers"</span>: [
          { <span class="tok-key">"name"</span>: <span class="tok-str">"Extra Shot"</span>, <span class="tok-key">"extra_price"</span>: <span class="tok-num">0</span> }
        ]
      }
    ],
    <span class="tok-key">"payments"</span>: [
      { <span class="tok-key">"method"</span>: <span class="tok-str">"Tunai"</span>, <span class="tok-key">"amount"</span>: <span class="tok-num">40000</span>, <span class="tok-key">"reference_code"</span>: <span class="tok-bool">null</span> }
    ]
  }
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VOID -->
                <div class="endpoint-card" id="ep-trx-void">
                    <div class="endpoint-header">
                        <span class="method-badge mb-post">POST</span>
                        <div style="flex:1">
                            <div class="endpoint-path">/api/v1/mobile/transactions/<em>{transaction}</em>/void</div>
                            <div class="endpoint-meta" style="margin-top:6px;">
                                <span class="role-badge role-owner">Owner Only</span>
                            </div>
                        </div>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-desc">Membatalkan (void) transaksi yang sudah berstatus <code>completed</code>. Hanya owner yang dapat melakukan ini. Stok akan dikembalikan secara otomatis.</p>

                        <div class="param-section">
                            <div class="param-label">Path Parameter</div>
                            <table class="param-table">
                                <thead><tr><th>Parameter</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="param-name">transaction</span> <span class="param-required">WAJIB</span></td><td><code>integer</code></td><td>ID transaksi yang akan di-void</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Contoh Request (cURL)</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code"><span class="tok-curl-flag">curl</span> -X POST https://yourdomain.com/api/v1/mobile/transactions/42/void \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Authorization: Bearer {token}"</span> \
  <span class="tok-curl-flag">-H</span> <span class="tok-str">"Accept: application/json"</span></pre>
                            </div>
                        </div>

                        <div class="code-block-wrap" x-data="clipboardBlock()">
                            <div class="code-block-label">Response 200 OK</div>
                            <div class="code-block">
                                <button class="copy-btn" @click="copy" :class="{ copied: copied }" x-text="copied ? '✓ Disalin' : 'Salin'">Salin</button>
                                <pre x-ref="code">{
  <span class="tok-key">"message"</span>: <span class="tok-str">"Transaksi berhasil di-void."</span>,
  <span class="tok-key">"transaction_id"</span>: <span class="tok-num">42</span>,
  <span class="tok-key">"code"</span>: <span class="tok-str">"TRX-2026-001"</span>,
  <span class="tok-key">"status"</span>: <span class="tok-str">"voided"</span>
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="docs-footer">
                    <div class="docs-footer-inner">
                        <span class="footer-brand">SAPI</span>
                        <span class="footer-copy">© {{ date('Y') }} SAPI — Kasir Pintar untuk UMKM Indonesia</span>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function clipboardBlock() {
            return {
                copied: false,
                copy() {
                    const text = this.$refs.code.innerText;
                    navigator.clipboard.writeText(text).then(() => {
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 2000);
                    });
                }
            };
        }

        // Highlight active sidebar link on scroll
        (function () {
            const links = document.querySelectorAll('.docs-sidebar .sidebar-link[href^="#"]');
            if (!links.length) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        links.forEach(l => {
                            l.classList.toggle('active', l.getAttribute('href') === '#' + id);
                        });
                    }
                });
            }, { rootMargin: '-60px 0px -70% 0px' });

            document.querySelectorAll('[id]').forEach(el => observer.observe(el));
        })();
    </script>
</body>
</html>
