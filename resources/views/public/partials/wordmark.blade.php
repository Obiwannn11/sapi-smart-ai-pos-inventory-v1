{{--
    Wordmark resmi: SAPI POS.

    Sampai sekarang ada dua identitas berbeda di permukaan publik — landing dan
    /api-docs memakai "SAPI" telanjang, sementara /dokumentasi memakai
    "SAPI POS" bertingkat. Review pemilik memilih yang kedua, jadi bentuk itulah
    yang dipakai bertiga.

    Warnanya diambil dari token palet bersama (`partials/theme`), bukan dari
    skala abu-abu Tailwind, supaya wordmark ikut bergeser bila paletnya berubah.

    Parameter:
    - $size        'sm' (topbar dokumentasi) | 'md' (nav) | 'lg' (footer)
    - $interactive true bila induknya `.group` dan wordmark ikut berwarna saat hover
--}}
@php
    $size = $size ?? 'md';
    $interactive = $interactive ?? false;

    $sapiSize = match ($size) {
        'sm' => 'text-[17px]',
        'lg' => 'text-2xl',
        default => 'text-xl md:text-2xl',
    };
    $posSize = $size === 'sm' ? 'text-[10px]' : 'text-[11px]';
@endphp
<span class="inline-flex items-baseline gap-[7px]">
    <span class="{{ $sapiSize }} font-extrabold tracking-tighter text-[var(--text)] {{ $interactive ? 'transition-colors group-hover:text-[var(--green-cta)]' : '' }}">SAPI</span>
    <span class="{{ $posSize }} font-bold uppercase tracking-[0.12em] text-[var(--text-dim)]">POS</span>
</span>
