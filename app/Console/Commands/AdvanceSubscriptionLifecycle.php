<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Menerbitkan tagihan periode berikutnya, lalu memindahkan tenant ke keadaan
 * berikutnya begitu tenggatnya lewat.
 *
 * Dijalankan harian. Tanpa ini, masa coba dan periode berbayar akan berakhir
 * di atas kertas saja: kolom tanggalnya lewat, tapi tenant tetap berstatus
 * `trial` selamanya dan tidak satu pun batas berlaku.
 *
 * Urutan keduanya dijaga di dalam `advanceLifecycle()`, bukan di jadwal —
 * tagihan harus terbit sebelum tenant kehilangan kemampuan menulis, dan urutan
 * yang bersandar pada dua baris jadwal akan salah pada hari seseorang
 * menggesernya.
 */
class AdvanceSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:advance-lifecycle {--dry-run : Tampilkan jumlah yang akan berpindah tanpa mengubah apa pun}';

    protected $description = 'Terbitkan tagihan periode berikutnya, pindahkan tenant yang periodenya lewat ke masa tenggang, dan yang tenggangnya habis ke penangguhan';

    public function handle(SubscriptionService $subscriptions): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $subscriptions->advanceLifecycle($dryRun);

        $suffix = $dryRun ? ' (dry-run)' : '';

        // Dilaporkan LEBIH DULU karena memang berjalan lebih dulu — tagihan
        // pertama tenant yang baru lulus masa gratis lahir di baris berikutnya,
        // dan urutan keluaran yang mengikuti urutan kejadian membuat keduanya
        // bisa dicocokkan tanpa membaca kode.
        $this->line(sprintf(
            'Masa gratis habis → paket bayar  : %d tenant%s',
            $result['graduated'],
            $suffix
        ));

        // Sama tajamnya dengan "tanpa tarif": tenant ini tetap menempuh masa
        // tenggang lalu tertangguh, dan sebabnya satu kotak centang yang tak
        // pernah ditandai. Ia tidak boleh terbaca sebagai angka wajar.
        if ($result['stranded'] > 0) {
            $this->warn(sprintf(
                'Tak ada paket tujuan             : %d tenant%s — masa gratisnya habis tapi tak satu pun paket ditandai "tujuan setelah masa gratis". Tandai satu di /platform/pricing-rules.',
                $result['stranded'],
                $suffix
            ));
        }

        $this->line(sprintf('Tagihan periode berikutnya terbit : %d%s', $result['invoiced'], $suffix));

        // Keduanya ditonjolkan, bukan dibariskan bersama yang lain: tenant yang
        // tak bisa ditagih adalah tenant yang tetap menempuh masa tenggang lalu
        // tertangguh tanpa pernah melihat tagihan. Itu tidak boleh terbaca
        // sebagai angka wajar di tengah daftar.
        if ($result['free'] > 0) {
            $this->warn(sprintf(
                'Tarifnya Rp 0, tidak ditagih      : %d tenant%s — tetapkan tarif paketnya di /platform/pricing-rules.',
                $result['free'],
                $suffix
            ));
        }

        // Dilaporkan sebagai baris biasa, bukan peringatan: bagi tenant Adaptif
        // berjangkar tanggal 1–7 ini keadaan yang WAJAR dan berulang tiap bulan
        // ([BL-080] opsi (i)). Tagihannya terbit begitu ringkasan omzet bulan
        // penentu tarifnya ditulis, beberapa hari lagi. Yang tidak wajar punya
        // barisnya sendiri di bawah.
        if ($result['postponed'] > 0) {
            $this->line(sprintf(
                'Menunggu ringkasan omzet          : %d tenant%s — tertunda, bukan gagal; terbit begitu omzet bulan penentunya dihitung.',
                $result['postponed'],
                $suffix
            ));
        }

        // Ini yang tidak wajar: hari jatuh tempo sudah lewat dan ringkasannya
        // tidak pernah tiba. Tagihannya sengaja ditahan daripada diterbitkan
        // dari paket penampung — menagih tenant subsidi dengan tarif termahal
        // karena sebuah cron gagal adalah kesalahan yang jauh lebih mahal
        // daripada tagihan yang terlambat. Surel peringatannya sudah dikirim.
        if ($result['overdue'] > 0) {
            $this->warn(sprintf(
                'Ringkasan omzet tak kunjung ada   : %d tenant%s — jatuh tempo terlewat dan tagihannya DITAHAN. Periksa `subscriptions:compute-revenue`, atau apakah tenantnya mencabut persetujuan subsidi.',
                $result['overdue'],
                $suffix
            ));
        }

        if ($result['unpriced'] > 0) {
            $this->warn(sprintf(
                'Tanpa tarif sama sekali           : %d tenant%s — tak ada bracket yang cocok dan tak ada paket penampung. Periksa /platform/pricing-rules.',
                $result['unpriced'],
                $suffix
            ));
        }

        // Dulu tenant ini hilang dari seluruh hitungan, dan itulah yang membuat
        // `[BL-058]` tak terlihat: keluaran perintahnya terbaca normal sementara
        // satu tenant lewat tanpa ditagih. Sekarang ia bersuara. Angka > 0
        // biasanya wajar — tagihan bulan itu memang sudah pernah terbit — tapi
        // ia harus bisa dicocokkan, bukan disimpulkan.
        if ($result['skipped'] > 0) {
            $this->line(sprintf(
                'Sudah punya tagihan langganan     : %d tenant%s — dilewati, bukan gagal.',
                $result['skipped'],
                $suffix
            ));
        }

        $this->line(sprintf(
            'Periode lewat  → masa tenggang : %d tenant%s',
            $result['expired'],
            $dryRun ? ' (dry-run)' : ''
        ));

        $this->line(sprintf(
            'Tenggang habis → penangguhan   : %d tenant%s',
            $result['suspended'],
            $dryRun ? ' (dry-run)' : ''
        ));

        $this->line(sprintf(
            'Consent dicabut → jalur normal : %d tenant%s',
            $result['reverted'],
            $dryRun ? ' (dry-run)' : ''
        ));

        // Ditonjolkan, bukan dibariskan bersama yang lain: ini satu-satunya
        // baris yang menaikkan tagihan seseorang. Tenant yang omzetnya melewati
        // ambang keluar dari keringanan pada periode berikutnya, dan pemilik
        // SaaS sebaiknya tahu itu terjadi pada hari ia ditandai — bukan pada
        // hari tagihannya terbit dua kali lipat.
        if ($result['ceiling_exits'] > 0) {
            $this->warn(sprintf(
                'Omzet lewat ambang Adaptif        : %d tenant%s — dijadwalkan pindah ke paket penampung pada periode berikutnya.',
                $result['ceiling_exits'],
                $suffix
            ));
        }

        // Dilaporkan meski nol. Pelepasan seat menurunkan tagihan bulan
        // berikutnya, dan satu-satunya cara memastikan ia benar-benar berjalan
        // adalah melihat angkanya bergerak di hari yang dijanjikan kepada
        // tenant — bukan menyimpulkannya dari tagihan yang sudah telanjur salah.
        $this->line(sprintf(
            'Pelepasan seat berlaku         : %d langganan%s',
            $result['seats_released'],
            $dryRun ? ' (dry-run)' : ''
        ));

        $this->info($dryRun
            ? 'Tidak ada yang diubah.'
            : 'Selesai.');

        return self::SUCCESS;
    }
}
