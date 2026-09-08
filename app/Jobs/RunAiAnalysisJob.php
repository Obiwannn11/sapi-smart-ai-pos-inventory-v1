<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Tenant;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiQuota;
use App\Services\AiContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class RunAiAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(public int $analysisId) {}

    public function handle(AiContextService $context, AiProviderFactory $factory): void
    {
        // withoutGlobalScopes: Job jalan tanpa auth(), scope tenant manual via relasi.
        $analysis = AiAnalysis::withoutGlobalScopes()->findOrFail($this->analysisId);
        $tenant = $analysis->tenant;

        // Defense-in-depth: job berjalan tanpa middleware, dan bisa sudah
        // mengantre saat flag dimatikan. Diperiksa SEBELUM kuota dan sebelum
        // provider dipanggil — terbalik berarti tenant yang fiturnya mati
        // tetap menghabiskan jatah hariannya.
        if (! $tenant->hasFeature('ai')) {
            $analysis->update([
                'status' => AiAnalysis::STATUS_FAILED,
                'error' => 'Fitur AI tidak aktif untuk outlet ini.',
            ]);

            return;
        }

        $analysis->update(['status' => AiAnalysis::STATUS_PROCESSING]);

        // AiContextService & ProfitService memakai TenantScope berbasis auth().
        // Job tak punya sesi, jadi autentikasi sebagai pemilik analisis agar seluruh
        // query konteks ter-scope ke tenant yang benar.
        Auth::setUser($analysis->user);

        try {
            $usingFreeTier = $factory->isUsingFreeTier($tenant);
            if ($usingFreeTier) {
                $this->assertQuota($tenant);
            }

            $from = Carbon::parse($analysis->params['from']);
            $to = Carbon::parse($analysis->params['to'])->endOfDay();

            $data = $context->buildContext($tenant, $from, $to);
            [$system, $user] = $this->prompts($analysis->type, $analysis->prompt, $tenant->tax_enabled);

            $result = $factory->for($tenant)->generate($system, $data, $user);

            $analysis->update([
                'status' => AiAnalysis::STATUS_COMPLETED,
                'result' => $result->text,
                'tokens_used' => $result->tokensUsed,
                // Dicatat bersama hasilnya, bukan diturunkan lagi saat dibaca
                // ([BL-100] tahap 2): hanya di sini diketahui nama mana yang
                // BENAR-BENAR disodorkan ke model. Konteksnya sendiri tidak
                // disimpan, dan tanpa daftar ini nama karangan model tidak
                // bisa dibedakan dari nama yang barangnya sudah dihapus.
                'context_variants' => $this->contextVariantNames($data),
            ]);

            if ($usingFreeTier) {
                $this->incrementUsage($tenant);
            }
        } catch (\Throwable $e) {
            $analysis->update([
                'status' => AiAnalysis::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        } finally {
            Auth::forgetGuards();
        }
    }

    /**
     * Nama varian yang benar-benar muncul di payload konteks.
     *
     * Ketiga sumbernya adalah tempat nama barang betul-betul tertulis di
     * konteks: dua rekap penjualan yang dikelompokkan produk dan varian, dan
     * daftar varian di tiap peringatan stok. Bagian konteks yang lain — omzet,
     * tren harian, proyeksi — tidak menyebut satu pun nama barang.
     *
     * Nama produk ikut dipungut bersama nama variannya. Keduanya sama-sama
     * disodorkan ke model, dan daftar yang hanya memuat separuhnya akan
     * menandai "Cafe Latte" sebagai karangan padahal ia justru bagian yang
     * paling jelas ada di data.
     *
     * Sengaja TIDAK menyisir seluruh array mencari kunci `variant_name`:
     * penyisir seperti itu akan diam-diam ikut memungut nama dari bagian
     * konteks yang kelak ditambahkan, termasuk yang tidak pernah sampai ke
     * mata model.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function contextVariantNames(array $data): array
    {
        $names = [];

        foreach ($data['top_products'] ?? [] as $row) {
            $names[] = $row['product_name'] ?? null;
            $names[] = $row['variant_name'] ?? null;
        }

        foreach ($data['profit_by_item']['items'] ?? [] as $row) {
            $names[] = $row['product_name'] ?? null;
            $names[] = $row['variant_name'] ?? null;
        }

        foreach ($data['inventory'] ?? [] as $badge) {
            foreach ($badge['items'] ?? [] as $item) {
                $names[] = $item['variant_name'] ?? null;
            }
        }

        $names = array_filter(
            array_map(fn ($name) => is_string($name) ? trim($name) : '', $names),
            fn (string $name) => $name !== '',
        );

        return array_values(array_unique($names));
    }

    /**
     * Batasnya datang dari paket langganan tenant, bukan lagi dari satu angka
     * yang sama untuk semua orang — lihat `App\Services\Ai\AiQuota`.
     */
    private function assertQuota(Tenant $tenant): void
    {
        $quota = app(AiQuota::class);
        $limit = $quota->dailyLimitFor($tenant);

        if ($limit <= 0) {
            throw new RuntimeException('Paket ini tidak menyertakan analisis AI. Isi API key sendiri di Pengaturan, atau naikkan paket.');
        }

        if ($quota->usedTodayBy($tenant) >= $limit) {
            throw new RuntimeException("Kuota AI hari ini habis ({$limit}/hari). Isi API key sendiri di Pengaturan untuk pemakaian tanpa batas.");
        }
    }

    /**
     * Naikkan hitungan pemakaian hari ini.
     *
     * Barisnya dicari dengan `whereDate`, bukan `firstOrCreate` berkunci
     * tanggal, dan bedanya bukan gaya penulisan: kolom `date` tersimpan sebagai
     * datetime, sehingga `where('date', '2026-08-01')` tidak pernah cocok
     * dengan baris berisi `2026-08-01 00:00:00`. `firstOrCreate` yang tidak
     * menemukan barisnya lalu mencoba menyisipkan baris kedua untuk (tenant,
     * tanggal) yang sama — dan indeks uniknya menolak. Akibatnya analisis KEDUA
     * seorang tenant di hari yang sama gagal, padahal jatahnya masih ada.
     */
    private function incrementUsage(Tenant $tenant): void
    {
        $usage = AiUsage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', now())
            ->first();

        if ($usage === null) {
            $usage = AiUsage::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'date' => now()->toDateString(),
                'count' => 0,
            ]);
        }

        $usage->increment('count');
    }

    /**
     * System prompt + pertanyaan untuk satu analisis.
     *
     * **Kenapa panjang.** Versi pertama hanya meminta jawaban "ringkas,
     * actionable, dengan angka konkret", dan yang kembali adalah saran yang
     * benar untuk kafe mana pun di dunia: "perbaiki layanan dan atmosfer",
     * "diversifikasi menu", "tingkatkan promosi". Kalimat seperti itu lolos
     * dari permintaan "actionable" karena bentuknya memang kalimat perintah —
     * yang tidak ada di dalamnya adalah objek, angka dasar, dan dampak.
     * Larangannya karena itu ditulis sebagai bentuk yang ditolak, bukan sebagai
     * ajakan lebih spesifik, dan tiap rekomendasi diwajibkan menyebut empat
     * hal sekaligus. Owner tidak membayar kuota AI untuk membaca nasihat yang
     * bisa ditulis tanpa membuka datanya.
     *
     * **Peta datanya ikut dikirim.** Tanpa itu model menebak arti kunci —
     * `others` terbaca sebagai nama produk, `projection` dihitung ulang dengan
     * rumusnya sendiri, dan `revenue` dipakai sebagai dasar margin.
     *
     * **Kalimat pajak hanya ikut untuk tenant yang memungut.** Payload profit
     * membawa `revenue` (dibayar pelanggan) DAN `net_revenue` (pendapatan toko)
     * berdampingan sejak `[BL-065]`, dan model yang mengurangi COGS dari angka
     * pertama akan melaporkan margin yang terlalu tinggi. Menaruh kalimatnya
     * tanpa syarat berarti menjelaskan pajak kepada mayoritas tenant yang tidak
     * memungut apa pun.
     *
     * @return array{0: string, 1: string} [systemPrompt, userPrompt]
     */
    private function prompts(string $type, ?string $custom, bool $taxEnabled = false): array
    {
        $system = <<<'PROMPT'
        Kamu analis bisnis F&B yang sedang membaca data operasional satu toko. Jawab dalam Bahasa Indonesia.

        PETA DATA (JSON di bawah):
        - `sales` — omzet, jumlah transaksi, dan rata-rata nota periode ini.
        - `profit` — `net_revenue` (pendapatan toko), `cogs`, `gross_profit`, `margin_pct`. Pakai apa adanya, jangan dihitung ulang.
        - `projection` — proyeksi periode berikutnya: `avg_daily_profit` x `basis_days`. Pakai apa adanya.
        - `profit_by_item.items` — margin per produk+varian, urut qty menurun. `profit_by_item.others` adalah RINGKASAN varian yang tidak muat ditampilkan (`variants` = jumlahnya); ia bukan produk bernama "others" dan namanya tidak boleh disebut sebagai nama barang.
        - `top_products` — 10 produk+varian terlaris menurut qty.
        - Nama barang selalu dua bagian: `product_name` adalah barangnya ("Cafe Latte"), `variant_name` hanya penanda varian ("Hot", "Single", "Plain") yang dipakai ulang oleh banyak produk. `variant_name` yang berdiri sendiri tidak menunjuk barang mana pun.
        - `daily_trend` — omzet dan jumlah transaksi per tanggal.
        - `inventory` — peringatan stok: `low_stock`, `out_of_stock`, `dead_stock`, `expired`, `near_expiry`, `needs_review`, masing-masing dengan daftar variannya.

        ATURAN ISI:
        1. Setiap pernyataan bersandar pada angka yang ADA di DATA, dan angkanya ditulis di dalam kalimatnya (rupiah, qty, persen, atau tanggal). Kalimat tanpa angka pendukung jangan ditulis.
        2. Sebut barang dengan `product_name` beserta variannya — mis. "Cafe Latte (Iced)" — dan tulis keduanya PERSIS seperti di DATA. Jangan menyebut `variant_name` sendirian, jangan menyingkat, menggabungkan dua varian, atau mengarang nama.
        3. DILARANG memberi saran yang bisa ditempel ke toko mana pun tanpa membaca datanya — misalnya "tingkatkan pelayanan", "perbaiki atmosfer", "perbanyak promosi", "diversifikasi menu", "tingkatkan pengalaman pelanggan". Saran seperti ini dianggap jawaban gagal.
        4. Tiap rekomendasi wajib memuat empat hal: (a) objeknya — varian, tanggal, atau varian stok tertentu dari DATA; (b) tindakan yang bisa dikerjakan minggu ini; (c) angka dari DATA yang jadi dasarnya; (d) perkiraan dampaknya dalam rupiah atau persen, beserta cara menghitungnya secara singkat.
        5. Kalau DATA tidak cukup untuk menjawab sesuatu, tulis satu baris "Belum bisa dijawab dari data: ..." dan sebut data apa yang kurang. Jangan menambalnya dengan saran umum.
        6. Jangan mengarang angka, tanggal, produk, atau kejadian yang tidak ada di DATA. Jangan menyimpulkan sebab (ramai/sepi karena cuaca, hari libur, pesaing) — DATA tidak memuatnya.
        7. Periode pendek atau data tipis lebih baik dikatakan apa adanya daripada dijadikan tren.

        FORMAT:
        - Markdown sederhana: `##` untuk judul bagian, `-` untuk butir, `1.` `2.` `3.` untuk langkah berurutan. Nomori berurutan — jangan menulis "1." berulang-ulang.
        - Rupiah ditulis gaya Indonesia (Rp1.234.567), persen satu angka desimal.
        - Tanpa kalimat pembuka, penutup, atau permintaan maaf. Maksimal 400 kata.
        PROMPT;

        if ($taxEnabled) {
            $system .= "\n\nPAJAK: tenant ini memungut pajak. `revenue` adalah uang yang dibayar pelanggan dan sudah memuat pajak yang bukan milik toko, sedangkan `net_revenue` adalah pendapatan toko. Hitung margin dan seluruh saran dari `net_revenue`, jangan dari `revenue`.";
        }

        $user = match ($type) {
            AiAnalysis::TYPE_DISCOUNT => <<<'PROMPT'
            Tentukan varian mana yang layak didiskon dan berapa besarannya. Susun jawaban persis dalam bagian berikut:

            ## Kandidat Diskon
            Maksimal 5 varian. Tiap varian satu butir yang menyebut: nama varian, qty terjual, `margin_pct` sekarang, usulan diskon dalam persen, margin sesudah diskon, dan alasannya (misalnya bermargin tinggi sehingga kuat menanggung potongan, atau muncul di `inventory.dead_stock` / `near_expiry`). Diskon yang membuat margin varian jatuh di bawah 0% dilarang diusulkan.

            ## Jangan Didiskon
            Varian yang marginnya sudah tipis atau negatif, beserta angkanya.

            ## Titik Impas
            Untuk tiap kandidat: berapa tambahan qty yang harus terjual agar potongannya tertutup, dihitung dari margin per unit sebelum dan sesudah diskon.
            PROMPT,
            AiAnalysis::TYPE_PROFIT_PROJECTION => <<<'PROMPT'
            Jelaskan profit periode ini dan proyeksi periode berikutnya. Susun jawaban persis dalam bagian berikut:

            ## Profit Periode Ini
            `net_revenue`, `cogs`, `gross_profit`, dan `margin_pct` dari `profit`, apa adanya.

            ## Proyeksi
            `avg_daily_profit` dan `projected_next_period` dari `projection`, apa adanya, ditutup satu kalimat batas asumsinya (rata-rata harian yang diteruskan lurus, tanpa musiman).

            ## Pendorong & Penghambat
            Dari `profit_by_item`: varian yang paling besar menyumbang margin dan yang paling menggerusnya, dengan kontribusi rupiah masing-masing. Sebut juga margin `others` bila ekor katalognya lebih buruk daripada rata-rata.

            ## Tindakan
            Maksimal 5 langkah bernomor, masing-masing memenuhi aturan isi nomor 4.
            PROMPT,
            AiAnalysis::TYPE_CUSTOM => $custom
                ? "Jawab pertanyaan berikut HANYA dari DATA, dengan seluruh aturan isi di atas. Bila DATA tidak memuat jawabannya, katakan begitu dan sebut data apa yang kurang — jangan diganti saran umum.\n\nPERTANYAAN: {$custom}"
                : 'Beri insight bisnis dari DATA ini dengan seluruh aturan isi di atas.',
            default => <<<'PROMPT'
            Beri insight bisnis dari DATA ini. Susun jawaban persis dalam bagian berikut:

            ## Ringkasan
            3 butir keadaan periode ini beserta angkanya (omzet, jumlah transaksi, rata-rata nota, margin).

            ## Yang Menyimpang
            Hal yang keluar dari kebiasaan periode ini: tanggal dengan lonjakan atau anjlok pada `daily_trend` (sebut tanggal dan angkanya), varian bermargin rendah atau negatif pada `profit_by_item`, dan peringatan pada `inventory` beserta nama variannya. Kalau tidak ada yang menyimpang, tulis apa adanya — jangan dicari-cari.

            ## Tindakan
            Maksimal 5 langkah bernomor, masing-masing memenuhi aturan isi nomor 4.
            PROMPT,
        };

        return [$system, $user];
    }
}
