@extends('public.docs.layout')

@section('title', 'Dokumentasi')
@section('description', 'Dua jalur dokumentasi SAPI — panduan penggunaan untuk pemilik usaha dan kasir, serta dokumentasi developer untuk integrasi API dan AI.')

@section('body')
<div style="max-width: 960px; margin: 0 auto; padding: 64px 24px 96px;">

    <h1 style="font-size: 40px; font-weight: 800; letter-spacing: -0.035em; margin: 0 0 12px; line-height: 1.1;">
        Dokumentasi
    </h1>
    <p style="font-size: 16.5px; line-height: 1.7; color: var(--text-muted); max-width: 620px; margin: 0 0 48px;">
        Dua jalur untuk dua pembaca yang berbeda. Pilih yang sesuai dengan yang sedang Anda kerjakan.
    </p>

    <div style="display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        @foreach ($tracks as $slug => $track)
            <a href="{{ route('docs.show', ['track' => $slug]) }}"
               style="display: block; padding: 28px; border-radius: 18px; border: 1px solid var(--border);
                      background: var(--bg-card); text-decoration: none; transition: border-color .15s;"
               onmouseover="this.style.borderColor='var(--green)'"
               onmouseout="this.style.borderColor='var(--border)'">

                <span style="display:inline-block; padding: 4px 11px; border-radius: 999px;
                             background: var(--green-faint); color: var(--green-cta);
                             font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em;">
                    {{ $track['tagline'] }}
                </span>

                <h2 style="font-size: 22px; font-weight: 800; letter-spacing: -0.02em; margin: 14px 0 8px;">
                    {{ $track['label'] }}
                </h2>

                <p style="font-size: 14.5px; line-height: 1.65; color: var(--text-muted); margin: 0 0 18px;">
                    {{ $track['summary'] }}
                </p>

                <div style="border-top: 1px solid var(--border-faint); padding-top: 14px;">
                    @foreach ($track['pages'] as $pageSlug => $page)
                        <div style="font-size: 13.5px; font-weight: 600; color: var(--text-muted); padding: 4px 0;">
                            {{ $page['title'] }}
                        </div>
                    @endforeach
                </div>
            </a>
        @endforeach
    </div>

    {{-- Referensi API berdiri sendiri di luar dua jalur: bentuknya bukan prosa
         melainkan katalog endpoint, dan ia sudah punya halamannya sendiri. --}}
    <div style="margin-top: 48px; padding: 24px 28px; border-radius: 16px;
                border: 1px dashed var(--border); background: var(--bg-card);
                display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 5px;">Referensi API Mobile POS</h3>
            <p style="font-size: 14px; line-height: 1.6; color: var(--text-muted); margin: 0;">
                Katalog lengkap endpoint REST berikut contoh permintaan dan jawabannya — autentikasi, kas, produk, dan transaksi.
            </p>
        </div>
        <a href="{{ route('api-docs') }}"
           style="padding: 10px 20px; border-radius: 11px; background: var(--green-cta); color: #fff;
                  font-size: 13.5px; font-weight: 700; text-decoration: none; white-space: nowrap;">
            Buka Referensi
        </a>
    </div>

    <p style="margin-top: 40px; font-size: 13.5px; color: var(--text-dim); line-height: 1.7;">
        Ada yang belum terjawab di sini? Kirimkan pertanyaan Anda lewat halaman kontak di
        <a href="{{ route('landing') }}" style="color: var(--green-cta); font-weight: 600;">beranda</a> —
        pertanyaan yang berulang biasanya berakhir jadi halaman baru di dokumentasi ini.
    </p>

</div>
@endsection
