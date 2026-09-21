@extends('public.docs.layout')

@section('title', $pageMeta['title'])
@section('description', $pageMeta['summary'])

@section('body')
<div class="docs-shell">

    <aside class="docs-aside" :class="{ 'is-open': aside }">
        @foreach (config('docs.tracks') as $trackSlug => $trackData)
            <div class="aside-group">
                <div class="aside-label">{{ $trackData['label'] }}</div>
                @foreach ($trackData['pages'] as $pageSlug => $pageData)
                    <a href="{{ route('docs.show', ['track' => $trackSlug, 'page' => $pageSlug]) }}"
                       class="aside-link {{ $trackSlug === $track && $pageSlug === $page ? 'is-active' : '' }}"
                       @click="aside = false">
                        {{ $pageData['title'] }}
                    </a>
                @endforeach
            </div>
        @endforeach

        <div class="aside-group">
            <div class="aside-label">Lainnya</div>
            <a href="{{ route('api-docs') }}" class="aside-link">Referensi API Mobile</a>
        </div>
    </aside>

    <main class="docs-main">
        <div class="docs-body">

            <button class="docs-aside-btn" @click="aside = !aside"
                    x-text="aside ? 'Tutup Daftar Isi' : 'Daftar Isi Dokumentasi'">
                Daftar Isi Dokumentasi
            </button>

            <p style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .11em;
                      color: var(--text-dim); margin: 0 0 10px;">
                {{ $trackMeta['label'] }}
            </p>

            {{-- Markdown sudah dirender di server dari berkas milik kita sendiri,
                 bukan masukan pengguna — jadi menyuntikkannya sebagai HTML aman
                 di sini, dan jauh lebih baik daripada menyalin penerjemah
                 markdown kedua ke sisi klien. --}}
            <article class="prose">{!! $html !!}</article>

            <div class="docs-nextprev">
                @if ($neighbours['prev'])
                    <a href="{{ route('docs.show', ['track' => $track, 'page' => $neighbours['prev']['slug']]) }}"
                       class="nextprev-card">
                        <div class="nextprev-label">Sebelumnya</div>
                        <div class="nextprev-title">{{ $neighbours['prev']['title'] }}</div>
                    </a>
                @endif

                @if ($neighbours['next'])
                    <a href="{{ route('docs.show', ['track' => $track, 'page' => $neighbours['next']['slug']]) }}"
                       class="nextprev-card" style="text-align: right;">
                        <div class="nextprev-label">Berikutnya</div>
                        <div class="nextprev-title">{{ $neighbours['next']['title'] }}</div>
                    </a>
                @endif
            </div>

        </div>
    </main>

</div>
@endsection
