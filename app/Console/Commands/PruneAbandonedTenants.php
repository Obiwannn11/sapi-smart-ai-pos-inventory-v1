<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Membuang tenant yang mendaftar lalu tidak pernah dipakai.
 *
 * **SENGAJA TIDAK DIJADWALKAN.** Menghapus catatan pelanggan otomatis tiap
 * malam adalah hal yang sebaiknya tidak berjalan tanpa ada yang menyadarinya.
 * Jalankan sendiri saat daftar tenant mulai terasa penuh, dan jalankan
 * `--dry-run` dulu.
 *
 * Syaratnya sempit dan harus terpenuhi SEMUA:
 *   1. pemiliknya tidak pernah memverifikasi alamat surelnya,
 *   2. tidak ada satu pun transaksi,
 *   3. umurnya melewati `subscription.abandoned_after_days`.
 *
 * Tenant yang pernah bertransaksi tidak pernah tersentuh perintah ini, berapa
 * pun umurnya — ada penjualan sungguhan di dalamnya.
 */
class PruneAbandonedTenants extends Command
{
    // Konfirmasi bawaan Laravel untuk perintah merusak: bertanya di produksi,
    // lewat begitu saja di lokal, dan bisa dilewati dengan --force. Pola yang
    // sama dipakai `migrate:fresh`.
    use ConfirmableTrait;

    protected $signature = 'platform:prune-abandoned-tenants
        {--dry-run : Tampilkan daftarnya tanpa menghapus}
        {--force : Jalankan tanpa konfirmasi di produksi}';

    protected $description = 'Buang tenant yang tak pernah diverifikasi dan tak pernah dipakai bertransaksi';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = (int) config('subscription.abandoned_after_days');
        $cutoff = now()->subDays($days);

        $candidates = Tenant::query()
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('users', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->get()
            ->filter(function (Tenant $tenant) {
                // withoutGlobalScopes() eksplisit: perintah konsol tidak punya
                // pengguna yang login, jadi TenantScope tidak aktif. Nyatakan
                // niatnya, lalu filter tenant_id sendiri.
                return ! Transaction::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->exists();
            });

        if ($candidates->isEmpty()) {
            $this->info("Tidak ada tenant terbengkalai yang lebih tua dari {$days} hari.");

            return self::SUCCESS;
        }

        foreach ($candidates as $tenant) {
            $this->line(sprintf(
                '%-30s daftar %s  IP %s',
                $tenant->name,
                $tenant->created_at->toDateString(),
                $tenant->signup_ip ?? '-',
            ));
        }

        if ($dryRun) {
            $this->info("{$candidates->count()} tenant akan dihapus (dry-run).");

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed("Akan menghapus {$candidates->count()} tenant beserta akunnya")) {
            return self::SUCCESS;
        }

        foreach ($candidates as $tenant) {
            $tenant->delete();
        }

        $this->info("{$candidates->count()} tenant dihapus.");

        return self::SUCCESS;
    }
}
