<?php

namespace App\Services\Upsell;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Models\UpsellRule;
use Illuminate\Support\Carbon;

/**
 * Apa yang SUNGGUH terjadi pada tiap aturan owner di kasir hari ini.
 *
 * Lahir dari satu keluhan yang tepat: tabel aturan menulis **Aktif** pada lima
 * baris sementara hanya dua yang sampai ke kasir. Kolom `is_active` menjawab
 * pertanyaan yang salah — ia menyatakan NIAT owner, bukan KEADAAN di kasir —
 * dan tidak ada satu pun layar yang menjawab yang kedua.
 *
 * Empat keadaan sebelumnya tidak terdeteksi sama sekali oleh pemeriksaan di
 * sisi klien yang menggantikan ini:
 *
 *   1. KALAH SLOT. Aturan yang lolos seluruh penjagaan tapi tergeser batas tiga
 *      slot dilaporkan "Tampil di kasir". Ini kebalikan dari kenyataan, dan
 *      satu-satunya keadaan yang MEMANG tidak bisa dijawab klien: ia menuntut
 *      indeks penuh beserta skor seluruh pesaingnya.
 *   2. Varian yang sudah KEDALUWARSA — gugur di `SellableVariantQuery`, tapi
 *      tidak pernah diperiksa layar.
 *   3. Produk yang DINONAKTIFKAN atau dihapus. Klien bahkan tidak memuat
 *      `is_active` produknya, jadi ia tidak punya bahan untuk memeriksanya.
 *   4. Jenis saran `manual` yang DIMATIKAN — entah oleh owner di Setelan atau
 *      oleh pemilik SaaS di `config/upsell.php`. Saat itu terjadi, SELURUH
 *      aturan berhenti muncul sekaligus, dan tabelnya tetap menulis "Aktif"
 *      pada semuanya.
 *
 * **Dijawab di server, dan itu bukan selera.** Hasilnya harus sependapat
 * dengan tab pratinjau sampai ke nomor slotnya, dan pratinjau memakai
 * `UpsellIndexBuilder` — kode yang sama persis dengan kasir. Perhitungan kedua
 * di klien akan berselisih dengan yang pertama pada hari pertama stok berubah,
 * dan owner yang melihat dua angka berbeda akan berhenti mempercayai keduanya.
 *
 * Satu hal yang TIDAK dijawab kelas ini: dua aturan yang menunjuk pasangan
 * pemicu–saran yang sama persis. Keduanya menghasilkan kunci yang sama, jadi
 * keduanya menerima status slot yang sama — dan itu nyaris benar, karena kasir
 * memang melihat saran itu satu kali. Yang tidak dijawab adalah aturan mana
 * dari keduanya yang sebenarnya mengisinya.
 */
class RuleOutcomeResolver
{
    /**
     * Nada status. Sengaja tiga, bukan satu per sebab: yang dijawab warna cuma
     * "perlu saya apa-apakan atau tidak". Pertanyaan "kenapa" dijawab tulisan.
     */
    private const TONE_LIVE = 'live';

    /** Disengaja, atau sekadar soal waktu — tidak ada yang perlu dikerjakan. */
    private const TONE_QUIET = 'quiet';

    /** Ada yang menghalangi, dan owner bisa membereskannya. */
    private const TONE_BLOCKED = 'blocked';

    public function __construct(private UpsellIndexBuilder $upsellIndexBuilder) {}

    /**
     * Satu baris hasil per aturan, dalam urutan yang SAMA dengan tabel aturan.
     *
     * Indeksnya diterima, bukan dirakit sendiri: merakitnya menelusuri seluruh
     * katalog, stok, dan riwayat penjualan, dan pemanggilnya sudah memegangnya
     * untuk tab pratinjau. Dua perakitan dalam satu permintaan membayar harga
     * yang sama dua kali untuk jawaban yang identik.
     *
     * @param  array{by_variant: array<int, list<array<string, mixed>>>, cart_level: list<array<string, mixed>>, max_per_transaction: int, mandatory: bool, generated_at: string}  $index
     * @return list<array<string, mixed>>
     */
    public function resolve(Tenant $tenant, array $index): array
    {
        $rules = UpsellRule::with([
            'triggerVariant:id,product_id,name',
            'triggerVariant.product:id,name,is_active',
            // `is_active` dan `expiry_date` ikut DI SINI, bukan di query tabel:
            // tanpa keduanya dua dari empat sebab di docblock kelas ini tidak
            // bisa diperiksa sama sekali.
            'suggestedVariant:id,product_id,name,price,stock,expiry_date',
            'suggestedVariant.product:id,name,is_active',
        ])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $placements = $this->placements($index);

        return $rules->map(fn (UpsellRule $rule) => $this->outcomeFor($tenant, $rule, $placements))->all();
    }

    /**
     * @param  array{cart: array<string, array{slot: int|null, lost_to: string|null}>, trigger: array<int, array<string, array{slot: int|null, lost_to: string|null}>>}  $placements
     * @return array<string, mixed>
     */
    private function outcomeFor(Tenant $tenant, UpsellRule $rule, array $placements): array
    {
        $label = $this->displayName($rule->suggestedVariant);

        $verdict = $this->verdict($tenant, $rule, $placements);

        return [
            'rule_id' => $rule->id,
            'label' => $label,
            ...$verdict,
        ];
    }

    /**
     * Sebab pertama yang menggigit, dan urutannya menentukan artinya.
     *
     * Saklar owner lebih dulu dari segalanya: aturan yang ia matikan sendiri
     * tidak perlu diberi tahu bahwa stoknya juga habis. Sesudahnya saklar
     * sistem, lalu jendela tanggal, lalu penjagaan kandidat, dan paling akhir
     * perebutan slot — satu-satunya yang baru bisa dijawab setelah semua
     * penjagaan lolos.
     *
     * @param  array{cart: array<string, array{slot: int|null, lost_to: string|null}>, trigger: array<int, array<string, array{slot: int|null, lost_to: string|null}>>}  $placements
     * @return array<string, mixed>
     */
    private function verdict(Tenant $tenant, UpsellRule $rule, array $placements): array
    {
        if (! $rule->is_active) {
            return $this->dormant('off', 'Dimatikan', 'Anda mematikan aturan ini sendiri.', self::TONE_QUIET,
                // Tidak masuk daftar "tidak muncul, dan kenapa": owner yang
                // menekan Matikan tidak sedang bertanya kenapa ia tidak muncul.
                attention: false);
        }

        if (! config('upsell.enabled', true)) {
            return $this->dormant('system_off', 'Saran jual mati', 'Seluruh fitur saran jual sedang dimatikan di setelan sistem.', self::TONE_QUIET,
                // Spanduk di kepala halaman sudah mengatakannya sekali. Mengulang
                // kalimat yang sama sebanyak jumlah aturan tidak menambah satu
                // pun keterangan baru.
                attention: false);
        }

        if (! config('upsell.types.'.UpsellEvent::TYPE_MANUAL, true)) {
            return $this->dormant(
                'type_unavailable',
                'Jenis mati untuk semua toko',
                'Jenis saran "pilihan pemilik" sedang dimatikan untuk seluruh toko, jadi tidak ada aturan yang muncul.',
                self::TONE_QUIET,
                // TANPA tombol perbaikan, dan itu disengaja ([BL-099]): tidak ada
                // layar yang bisa dibuka owner untuk mengubahnya, dan menawarkan
                // jalan yang tidak bisa ditempuh terbaca seperti izin.
            );
        }

        if (! $tenant->upsellTypeEnabled(UpsellEvent::TYPE_MANUAL)) {
            return $this->dormant(
                'type_disabled',
                'Jenis Anda matikan',
                'Anda mematikan jenis saran "pilihan pemilik" di Setelan, jadi tidak ada aturan yang muncul.',
                self::TONE_BLOCKED,
                fix: ['label' => 'Nyalakan', 'href' => route('owner.settings.operations.index', absolute: false)],
            );
        }

        $today = now();

        if ($rule->starts_on !== null && $rule->starts_on->startOfDay()->gt($today->copy()->startOfDay())) {
            return $this->dormant(
                'not_started',
                'Belum mulai',
                'Jendelanya baru mulai '.$this->longDate($rule->starts_on).'.',
                self::TONE_QUIET,
                // Tanpa href: yang membuka formulir edit aturan ini adalah layar,
                // bukan tautan — lihat pemakaiannya di Index.vue.
                fix: ['label' => 'Ubah jendela', 'href' => null],
            );
        }

        if ($rule->ends_on !== null && $rule->ends_on->startOfDay()->lt($today->copy()->startOfDay())) {
            return $this->dormant(
                'ended',
                'Sudah berakhir',
                'Jendelanya berakhir '.$this->longDate($rule->ends_on).'.',
                self::TONE_QUIET,
                fix: ['label' => 'Ubah jendela', 'href' => null],
            );
        }

        if (($triggerProblem = $this->triggerProblem($rule)) !== null) {
            return $triggerProblem;
        }

        if (($suggestedProblem = $this->suggestedProblem($rule)) !== null) {
            return $suggestedProblem;
        }

        return $this->slotVerdict($rule, $placements);
    }

    /**
     * Aturan berpemicu yang pemicunya tidak bisa dijual tidak akan pernah
     * menyala — kasir tidak punya cara memasukkan barang itu ke keranjang.
     *
     * Stok pemicu sengaja TIDAK diperiksa: pemicu yang stoknya habis hari ini
     * bisa terisi lagi sore nanti tanpa satu pun suntingan pada aturannya,
     * sedangkan produk nonaktif menuntut tindakan owner.
     *
     * @return array<string, mixed>|null
     */
    private function triggerProblem(UpsellRule $rule): ?array
    {
        if ($rule->trigger_variant_id === null) {
            return null;
        }

        $trigger = $rule->triggerVariant;

        if ($trigger === null || $trigger->product === null) {
            return $this->dormant('trigger_missing', 'Pemicunya hilang', 'Barang pemicunya sudah dihapus, jadi aturan ini tidak bisa menyala.', self::TONE_BLOCKED);
        }

        if (! $trigger->product->is_active) {
            return $this->dormant(
                'trigger_inactive',
                'Pemicunya nonaktif',
                'Produk pemicunya ('.$this->displayName($trigger).') sedang dinonaktifkan, jadi ia tidak bisa masuk keranjang.',
                self::TONE_BLOCKED,
                fix: $this->productFix($trigger),
            );
        }

        return null;
    }

    /**
     * Penjagaan kandidat `SellableVariantQuery`, diperiksa satu per satu supaya
     * sebabnya bisa disebut — bukan sekadar "tidak lolos".
     *
     * Kedaluwarsa diperiksa SEBELUM stok: barang yang stoknya nol sekaligus
     * kedaluwarsa adalah barang yang tidak boleh dijual, dan itu keterangan yang
     * lebih penting daripada stoknya.
     *
     * @return array<string, mixed>|null
     */
    private function suggestedProblem(UpsellRule $rule): ?array
    {
        $variant = $rule->suggestedVariant;

        if ($variant === null || $variant->product === null) {
            return $this->dormant('variant_missing', 'Barangnya hilang', 'Barang yang disarankan sudah dihapus. Aturan ini perlu ditulis ulang atau dihapus.', self::TONE_BLOCKED);
        }

        if (! $variant->product->is_active) {
            return $this->dormant(
                'product_inactive',
                'Produknya nonaktif',
                'Produknya sedang dinonaktifkan, jadi ia tidak boleh ditawarkan.',
                self::TONE_BLOCKED,
                fix: $this->productFix($variant),
            );
        }

        if ($variant->expiry_date !== null && $variant->expiry_date->startOfDay()->lt(now()->startOfDay())) {
            return $this->dormant(
                'expired',
                'Sudah kedaluwarsa',
                'Barangnya kedaluwarsa '.$this->longDate($variant->expiry_date).' dan tidak boleh dijual dalam bentuk apa pun.',
                self::TONE_BLOCKED,
                fix: $this->productFix($variant),
            );
        }

        if ((int) $variant->stock <= 0) {
            return $this->dormant(
                'out_of_stock',
                'Stok habis',
                'Stoknya nol, jadi kasir tidak menawarkannya.',
                self::TONE_BLOCKED,
                fix: $this->productFix($variant),
            );
        }

        return null;
    }

    /**
     * Lolos semua penjagaan — tinggal perebutan slotnya.
     *
     * @param  array{cart: array<string, array{slot: int|null, lost_to: string|null}>, trigger: array<int, array<string, array{slot: int|null, lost_to: string|null}>>}  $placements
     * @return array<string, mixed>
     */
    private function slotVerdict(UpsellRule $rule, array $placements): array
    {
        $key = Suggestion::keyFor(
            UpsellEvent::TYPE_MANUAL,
            $rule->trigger_variant_id,
            $rule->suggested_variant_id,
        );

        $place = $rule->trigger_variant_id === null
            ? ($placements['cart'][$key] ?? null)
            : ($placements['trigger'][$rule->trigger_variant_id][$key] ?? null);

        if ($place === null) {
            // Tidak seharusnya terjadi, jadi dikatakan apa adanya alih-alih
            // ditebak. Satu-satunya jalan yang diketahui ke sini: barang yang
            // disarankan sama dengan pemicunya, yang disaring `rankForCart()`
            // karena menyarankan isi keranjang kepada kasir terbaca asal-asalan.
            return $this->dormant(
                'not_ranked',
                'Tidak ikut perebutan',
                'Lolos semua penjagaan tapi tidak ikut perebutan slot — periksa apakah barang yang disarankan sama dengan pemicunya.',
                self::TONE_BLOCKED,
            );
        }

        if ($place['slot'] !== null) {
            return [
                'state' => 'live',
                'status' => 'Tampil · slot '.$place['slot'],
                'detail' => $rule->trigger_variant_id === null
                    ? 'Muncul di kasir pada setiap penjualan, di slot '.$place['slot'].'.'
                    : 'Muncul di kasir begitu barang pemicunya masuk keranjang, di slot '.$place['slot'].'.',
                'tone' => self::TONE_LIVE,
                'slot' => $place['slot'],
                'appears' => true,
                'needs_attention' => false,
                'fix' => null,
            ];
        }

        return $this->dormant(
            'lost_slot',
            'Kalah slot',
            $place['lost_to'] === null
                ? 'Tergeser batas slot kasir.'
                : 'Kalah dari '.$place['lost_to'].' — naikkan urutannya untuk menukar posisi.',
            self::TONE_QUIET,
            // Tidak masuk daftar "tidak muncul sama sekali": aturan ini SUDAH
            // terlihat di daftar slot tab pratinjau, bertanda "Tergeser". Ia
            // kalah, bukan hilang — dan menyebutnya di dua tempat sekaligus
            // membuat daftar yang satu-satunya gunanya adalah pendek jadi
            // panjang.
            attention: false,
        );
    }

    /**
     * @param  array{label: string, href: string|null}|null  $fix
     * @return array<string, mixed>
     */
    private function dormant(
        string $state,
        string $status,
        string $detail,
        string $tone,
        ?array $fix = null,
        bool $attention = true,
    ): array {
        return [
            'state' => $state,
            'status' => $status,
            'detail' => $detail,
            'tone' => $tone,
            'slot' => null,
            'appears' => false,
            'needs_attention' => $attention,
            'fix' => $fix,
        ];
    }

    /**
     * Posisi tiap saran dalam perebutan slot, dipetakan per kunci saran.
     *
     * Dua pita terpisah, dan pembelahannya mengikuti pratinjau: aturan tanpa
     * pemicu berebut di antara kandidat tanpa-pemicu saja, sedangkan aturan
     * berpemicu berebut di keranjang yang berisi pemicunya — yang juga memuat
     * kandidat tanpa-pemicu. Mencampur keduanya akan melaporkan nomor slot yang
     * tidak pernah dilihat kasir mana pun.
     *
     * @param  array{by_variant: array<int, list<array<string, mixed>>>, cart_level: list<array<string, mixed>>, max_per_transaction: int, mandatory: bool, generated_at: string}  $index
     * @return array{cart: array<string, array{slot: int|null, lost_to: string|null}>, trigger: array<int, array<string, array{slot: int|null, lost_to: string|null}>>}
     */
    private function placements(array $index): array
    {
        $max = (int) $index['max_per_transaction'];

        // Diurutkan di sini, bukan lewat `rankForCart()`: fungsi itu sengaja
        // mengembalikan kosong untuk keranjang kosong, dan ini memang keranjang
        // tanpa satu pun barang pemicu. Sama dengan yang dilakukan pratinjau.
        $cartLevel = $index['cart_level'];
        usort($cartLevel, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $byTrigger = [];

        foreach (array_keys($index['by_variant']) as $triggerId) {
            $triggerId = (int) $triggerId;

            $byTrigger[$triggerId] = $this->positions(
                $this->upsellIndexBuilder->rankForCart($index, [$triggerId]),
                $max,
            );
        }

        return [
            'cart' => $this->positions($cartLevel, $max),
            'trigger' => $byTrigger,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ranked
     * @return array<string, array{slot: int|null, lost_to: string|null}>
     */
    private function positions(array $ranked, int $max): array
    {
        $ranked = array_values($ranked);

        // Penghuni slot TERAKHIR yang masih tampil. Inilah yang harus dilewati
        // sebuah saran untuk ikut masuk, jadi inilah lawan yang berarti bagi
        // yang tergeser — bukan yang di puncak, yang mungkin tak terjangkau.
        $lastWinner = $ranked[$max - 1]['label'] ?? null;

        $positions = [];

        foreach ($ranked as $position => $suggestion) {
            $wins = $position < $max;

            $positions[$suggestion['key']] = [
                'slot' => $wins ? $position + 1 : null,
                'lost_to' => $wins ? null : $lastWinner,
            ];
        }

        return $positions;
    }

    /**
     * @return array{label: string, href: string}
     */
    private function productFix(ProductVariant $variant): array
    {
        return [
            'label' => 'Lihat produk',
            'href' => route('owner.products.show', $variant->product_id, absolute: false),
        ];
    }

    /**
     * Bulan PANJANG, berbeda dari tabel aturan yang memakai bulan pendek.
     *
     * Bukan ketidakkonsistenan: kalimat ini berdiri sendiri tanpa kolom tanggal
     * di sebelahnya, dan "Agustus" tidak pernah berselisih dengan singkatan
     * mana pun — sementara Carbon menyingkatnya "Agt" dan `Intl` di peramban
     * menyingkatnya "Agu". Dua singkatan berbeda untuk bulan yang sama, di satu
     * halaman, adalah tepat jenis cacat yang tidak pernah dilaporkan siapa pun
     * tapi membuat layarnya terasa tidak dirawat.
     */
    private function longDate(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->translatedFormat('j F Y');
    }

    private function displayName(?ProductVariant $variant): string
    {
        if ($variant === null) {
            return 'Barang yang sudah dihapus';
        }

        return $variant->product !== null
            ? $variant->product->name.' - '.$variant->name
            : $variant->name;
    }
}
