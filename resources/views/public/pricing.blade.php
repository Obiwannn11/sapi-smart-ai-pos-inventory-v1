{{--
    Halaman harga publik (`[BL-041]`(c)).

    Menumpang kerangka `/dokumentasi` — topbar, palet, dan gaya prosa yang sama
    — supaya tidak lahir sumber gaya keempat setelah `[BL-033]` susah payah
    menyatukan ketiganya.

    Tidak ada satu pun nominal yang diketik di berkas ini. Semua dari
    `PublicPricing`, yang membaca `plans` dan `pricing_rules`.
--}}
@extends('public.docs.layout')

@section('title', 'Harga')
@section('title-suffix', 'SAPI POS')
@section('description', 'Tarif langganan SAPI POS: jalur Harga Tetap per paket dan jalur Harga Adaptif yang mengikuti omzet.')
@section('topbar-title', 'Harga')
@section('topbar-href', route('pricing'))

@section('body')
    <style>
        .price-main { max-width: 940px; margin: 0 auto; padding: 48px 24px 96px; }
        .price-hero { max-width: 660px; margin-bottom: 48px; }
        .price-hero h1 { font-size: 38px; font-weight: 800; letter-spacing: -0.03em; margin: 0 0 14px; line-height: 1.15; }
        .price-hero p { font-size: 16px; line-height: 1.7; color: var(--text-muted); margin: 0; }
        .price-hero strong { color: var(--text); font-weight: 700; }

        .price-section { margin-bottom: 56px; }
        .price-section > h2 { font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin: 0 0 6px; }
        .price-section > .lede { font-size: 14.5px; line-height: 1.7; color: var(--text-muted); margin: 0 0 20px; max-width: 640px; }

        .price-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .price-table th {
            text-align: left; padding: 10px 14px;
            font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-dim); border-bottom: 1px solid var(--border);
        }
        .price-table td { padding: 13px 14px; border-bottom: 1px solid var(--border-faint); color: var(--text-muted); }
        .price-table tr:last-child td { border-bottom: none; }
        .price-table .cell-name { font-weight: 700; color: var(--text); }
        .price-table .cell-price { font-weight: 700; color: var(--text); white-space: nowrap; }
        .price-table-wrap { background: var(--bg-card); border: 1px solid var(--border-faint); border-radius: 14px; overflow-x: auto; }

        .price-tag {
            display: inline-block; margin-left: 8px; padding: 2px 8px; border-radius: 999px;
            background: var(--green-faint); color: var(--green-cta);
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;
        }

        .compare { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid var(--border-faint); border-radius: 14px; overflow: hidden; background: var(--bg-card); }
        .compare-col { padding: 24px; }
        .compare-col + .compare-col { border-left: 1px solid var(--border-faint); }
        .compare-col h3 { font-size: 15px; font-weight: 800; margin: 0 0 4px; }
        .compare-col .who { font-size: 13px; color: var(--text-dim); margin: 0 0 18px; }
        .compare dl { margin: 0; }
        .compare dt { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-dim); margin-bottom: 4px; }
        .compare dd { margin: 0 0 16px; font-size: 14px; line-height: 1.65; color: var(--text-muted); }
        .compare dd:last-child { margin-bottom: 0; }

        .price-note {
            margin-top: 20px; padding: 16px 18px;
            background: var(--green-faint); border-left: 3px solid var(--green);
            border-radius: 0 10px 10px 0;
            font-size: 14px; line-height: 1.7; color: var(--text);
        }
        .price-note strong { font-weight: 700; }

        .price-cta { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
        .price-cta a.primary {
            padding: 12px 22px; border-radius: 12px; background: var(--green-cta); color: #fff;
            font-size: 14px; font-weight: 700; text-decoration: none;
        }
        .price-cta a.primary:hover { background: var(--green); }
        .price-cta a.secondary { font-size: 14px; font-weight: 700; color: var(--text-muted); text-decoration: none; }
        .price-cta a.secondary:hover { color: var(--green-cta); }

        @media (max-width: 720px) {
            .compare { grid-template-columns: 1fr; }
            .compare-col + .compare-col { border-left: none; border-top: 1px solid var(--border-faint); }
        }
    </style>

    <main class="price-main">
        <div class="price-hero">
            <h1>Harga</h1>
            <p>
                Ada dua jalur tarif. <strong>Harga Tetap</strong> mengikuti paket yang Anda pilih.
                <strong>Harga Adaptif</strong> mengikuti omzet bulan lalu, dan ditujukan untuk usaha beromzet rendah.
                Angka di halaman ini dibaca langsung dari aturan yang berlaku — sama dengan yang akan ditagihkan.
            </p>
        </div>

        <section class="price-section">
            <h2>Jalur Harga Tetap</h2>
            <p class="lede">Tarif ditentukan paket, tidak berubah mengikuti ramai atau sepinya bulan.</p>

            <div class="price-table-wrap">
                <table class="price-table">
                    {{--
                        Istilahnya "Pengguna termasuk" / "Pengguna tambahan",
                        mengikuti label yang sudah dipakai `/langganan`
                        (`Billing/Show.vue`). Keputusan 2026-08-07 menamai
                        pasangannya "seat bawaan paket" vs "seat tambahan", tapi
                        kata "seat" tidak pernah muncul di satu pun layar tenant
                        — memakainya di sini justru akan jadi kata ketiga yang
                        dilarang `[BL-067]`(c).
                    --}}
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Tarif per bulan</th>
                            <th>Pengguna termasuk</th>
                            <th>Analisis AI per hari</th>
                            <th>Pengguna tambahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pricing['plans'] as $plan)
                            <tr>
                                <td class="cell-name">
                                    {{ $plan['name'] }}
                                    @if ($plan['is_post_trial_target'])
                                        <span class="price-tag">Paket lanjutan</span>
                                    @endif
                                </td>
                                <td class="cell-price">
                                    @if ($plan['is_free'])
                                        Gratis, {{ $pricing['trial_months'] }} bulan pertama
                                    @else
                                        Rp {{ number_format($plan['price'], 0, ',', '.') }}
                                    @endif
                                </td>
                                <td class="cell-price">{{ $plan['included_seats'] }}</td>
                                {{--
                                    `null` berarti paket ini tidak menyetel
                                    batasnya sendiri dan ikut bawaan platform —
                                    BUKAN nol. Menuliskannya "0" akan memajang
                                    paket yang tidak menjual AI padahal ia dapat
                                    jatah.
                                --}}
                                <td class="cell-price">
                                    @if ($plan['ai_daily'] === null)
                                        Ikut bawaan platform
                                    @else
                                        {{ $plan['ai_daily'] }}
                                    @endif
                                </td>
                                <td class="cell-price">
                                    @if ($plan['extra_seat_price'] <= 0)
                                        Gratis
                                    @else
                                        Rp {{ number_format($plan['extra_seat_price'], 0, ',', '.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="price-note">
                Masa gratis berlaku {{ $pricing['trial_months'] }} bulan. Sesudahnya akun berpindah sendiri ke paket lanjutan —
                tidak berhenti, dan tidak menunggu Anda melakukan apa pun.
            </div>

            {{--
                `[BL-067]`(d): batas yang tidak dijelaskan konsekuensinya akan
                dibaca sebagai batas keras yang memutus fitur. Dua kalimat ini
                menjawabnya sekaligus menyebut jalan keluarnya.

                Yang TIDAK boleh ada di sini: janji "beli tambahan kuota AI".
                Alur belinya belum berbentuk sama sekali — `[BL-067]`(e),
                menunggu `[BL-069]`.
            --}}
            <div class="price-note">
                <strong>Tentang batas analisis AI.</strong>
                Jatahnya berulang setiap hari. Bila habis, analisis berikutnya ditolak sampai besok — fitur lain di aplikasi
                tidak ikut berhenti. Anda juga bisa memakai API key sendiri, dan batas ini tidak lagi berlaku.
            </div>
        </section>

        @if ($pricing['adaptive']['ladder'] !== [])
            <section class="price-section">
                <h2>Jalur Harga Adaptif</h2>
                <p class="lede">
                    Tarif mengikuti omzet bulan lalu, dihitung otomatis dari transaksi yang tercatat di aplikasi —
                    bukan dari laporan yang Anda isi sendiri.
                </p>

                <div class="price-table-wrap">
                    <table class="price-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Omzet bulan lalu</th>
                                <th>Tarif per bulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pricing['adaptive']['ladder'] as $bracket)
                                <tr>
                                    <td class="cell-name">{{ $bracket['label'] }}</td>
                                    {{--
                                        Kata-katanya sengaja sama persis dengan
                                        `Billing/Adaptive.vue:49-52`. Dua halaman
                                        yang menjelaskan tangga yang sama tidak
                                        boleh menyebut rentang yang sama dengan
                                        dua cara berbeda.
                                    --}}
                                    <td>
                                        @if ($bracket['min'] === null && $bracket['max'] === null)
                                            Semua omzet
                                        @elseif ($bracket['min'] === null)
                                            Di bawah Rp {{ number_format($bracket['max'], 0, ',', '.') }}
                                        @elseif ($bracket['max'] === null)
                                            Rp {{ number_format($bracket['min'], 0, ',', '.') }} ke atas
                                        @else
                                            Rp {{ number_format($bracket['min'], 0, ',', '.') }} – di bawah Rp {{ number_format($bracket['max'], 0, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="cell-price">Rp {{ number_format($bracket['price'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{--
                    Ambangnya hanya disebut bila memang ada. `ceiling` null berarti
                    tangganya tidak berujung, BUKAN ambang nol — menuliskannya
                    sebagai "Rp 0" akan memberi tahu setiap pengunjung bahwa ia
                    tidak berhak atas jalur ini.
                --}}
                @if ($pricing['adaptive']['ceiling'] !== null)
                    <div class="price-note">
                        Harga Adaptif berlaku sampai omzet di bawah <strong>Rp {{ number_format($pricing['adaptive']['ceiling'], 0, ',', '.') }}</strong> per bulan.
                        Di atas itu jalur yang berlaku adalah Harga Tetap.
                        @if ($pricing['adaptive']['host_plan'] !== null)
                            Tenant di jalur ini menghuni paket <strong>{{ $pricing['adaptive']['host_plan'] }}</strong> dengan tarif yang didiskon menurut kelasnya.
                        @endif
                    </div>
                @endif
            </section>
        @endif

        <section class="price-section">
            <h2>Membandingkan keduanya</h2>
            <p class="lede">Apa yang dibuka, apa yang dibatasi, dan apa yang diminta dari Anda.</p>

            <div class="compare">
                <div class="compare-col">
                    <h3>Harga Tetap</h3>
                    <p class="who">Untuk yang ingin tarifnya bisa diperkirakan.</p>
                    <dl>
                        <dt>Tarif ditentukan</dt>
                        <dd>Oleh paket yang Anda pilih. Sama tiap bulan.</dd>

                        <dt>Data yang diserahkan</dt>
                        <dd>Tidak ada. Omzet Anda tidak ikut dihitung untuk penetapan tarif.</dd>

                        <dt>Syarat kelayakan</dt>
                        <dd>Tidak ada. Berapa pun omzetnya, jalur ini terbuka.</dd>

                        <dt>Cara masuk</dt>
                        <dd>Berlaku sejak mendaftar — ini jalur bawaan.</dd>
                    </dl>
                </div>

                <div class="compare-col">
                    <h3>Harga Adaptif</h3>
                    <p class="who">Untuk usaha beromzet rendah yang tarifnya terasa berat.</p>
                    <dl>
                        <dt>Tarif ditentukan</dt>
                        <dd>Oleh omzet bulan lalu, dihitung otomatis dari transaksi. Bisa berubah tiap bulan mengikuti kelasnya.</dd>

                        <dt>Data yang diserahkan</dt>
                        <dd>Persetujuan eksplisit agar total omzet bulanan Anda dipakai menetapkan tarif. Diminta sekali, dan bisa dibaca sebelum disetujui.</dd>

                        <dt>Syarat kelayakan</dt>
                        <dd>
                            @if ($pricing['adaptive']['ceiling'] !== null)
                                Omzet bulanan di bawah Rp {{ number_format($pricing['adaptive']['ceiling'], 0, ',', '.') }}. Dinilai saat Anda mengajukan.
                            @else
                                Dinilai saat Anda mengajukan.
                            @endif
                        </dd>

                        <dt>Cara masuk</dt>
                        <dd>Diajukan sendiri dari halaman langganan setelah akun aktif.</dd>
                    </dl>
                </div>
            </div>

            <div class="price-note">
                Perpindahan jalur bisa dilakukan setiap <strong>{{ $pricing['track_switch_minimum_months'] }} bulan</strong> sekali.
                Jarak itu ada supaya tarif tidak ikut naik-turun mengikuti bulan ramai dan sepi.
            </div>
        </section>

        <div class="price-cta">
            <a href="{{ route('register') }}" class="primary">Mulai Sekarang</a>
            <a href="{{ route('docs.show', ['track' => 'panduan', 'page' => 'langganan']) }}" class="secondary">Baca panduan langganan →</a>
        </div>
    </main>
@endsection
