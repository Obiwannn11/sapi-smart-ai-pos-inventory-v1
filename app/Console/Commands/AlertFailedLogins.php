<?php

namespace App\Console\Commands;

use App\Models\PlatformAuditLog;
use App\Services\PlatformAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Memberi tahu pemilik SaaS saat percobaan masuk yang gagal menumpuk.
 *
 * Ini lapisan KESADARAN, bukan pertahanan — penahanannya sudah ada di throttle
 * ([BL-007]) dan pencatatannya di jejak audit ([BL-009]). Yang hilang selama ini
 * adalah yang memberitahu: serangan yang berjalan pelan, di bawah ambang
 * throttle dan tersebar berjam-jam, akan terekam lengkap dan tetap tak terlihat
 * sampai ada yang kebetulan membuka halaman jejak audit.
 *
 * Cakupannya percobaan masuk ke PANEL PLATFORM saja. Login tenant yang gagal
 * belum dicatat di mana pun — sisi tenant tidak punya tabel audit sendiri, dan
 * membuatnya adalah pekerjaan tersendiri.
 */
class AlertFailedLogins extends Command
{
    protected $signature = 'platform:alert-failed-logins {--dry-run : Tampilkan temuan tanpa mengirim peringatan}';

    protected $description = 'Beri tahu pemilik SaaS bila percobaan masuk gagal ke panel platform menumpuk';

    public function handle(PlatformAlertService $alerts): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $config = config('platform-alerts.failed_login');
        $since = now()->subMinutes((int) $config['window_minutes']);

        $failures = PlatformAuditLog::query()
            ->where('action', 'login.failed')
            ->where('created_at', '>=', $since)
            ->get();

        if ($failures->isEmpty()) {
            $this->info('Tidak ada percobaan masuk yang gagal dalam rentang ini.');

            return self::SUCCESS;
        }

        $terkirim = 0;
        $terkirim += $this->alertPerEmail($failures, $config, $alerts, $dryRun);
        $terkirim += $this->alertPerIp($failures, $config, $alerts, $dryRun);

        $this->info($dryRun
            ? "{$terkirim} peringatan akan dikirim (dry-run)."
            : "{$terkirim} peringatan dikirim.");

        return self::SUCCESS;
    }

    /**
     * Pola pertama: banyak kegagalan pada SATU alamat — penebakan kata sandi
     * terhadap akun yang sudah diketahui keberadaannya.
     *
     * @param  Collection<int, PlatformAuditLog>  $failures
     * @param  array<string, int>  $config
     */
    private function alertPerEmail(Collection $failures, array $config, PlatformAlertService $alerts, bool $dryRun): int
    {
        $terkirim = 0;

        $perEmail = $failures
            ->groupBy(fn (PlatformAuditLog $log) => strtolower($log->meta['email'] ?? '-'))
            ->filter(fn (Collection $group) => $group->count() >= $config['per_email']);

        foreach ($perEmail as $email => $group) {
            $ips = $group->pluck('ip')->filter()->unique();

            $this->line(sprintf('Penebakan kata sandi: %s — %d kegagalan dari %d IP', $email, $group->count(), $ips->count()));

            if ($dryRun) {
                $terkirim++;

                continue;
            }

            $dikirim = $alerts->send(
                key: "failed-login.email:{$email}",
                subject: 'Percobaan masuk berulang ke panel platform',
                summary: sprintf(
                    'Ada %d percobaan masuk yang gagal untuk alamat %s dalam %d jam terakhir.',
                    $group->count(),
                    $email,
                    (int) ($config['window_minutes'] / 60),
                ),
                lines: [
                    'Berasal dari '.$ips->count().' alamat IP: '.$ips->take(5)->implode(', ').($ips->count() > 5 ? ', dan lainnya' : '').'.',
                    'Percobaan sudah dibatasi lajunya, jadi ini pemberitahuan — bukan tanda akun sudah jebol.',
                    'Bila alamat ini memang milik Anda dan Anda tidak sedang mencoba masuk, ganti kata sandinya.',
                ],
                meta: ['email' => $email, 'attempts' => $group->count(), 'ip_count' => $ips->count()],
                actionUrl: url('/platform/audit-logs'),
                actionLabel: 'Lihat Jejak Audit',
            );

            $terkirim += $dikirim ? 1 : 0;
        }

        return $terkirim;
    }

    /**
     * Pola kedua: banyak alamat BERBEDA dicoba dari satu IP — penebakan akun,
     * mencari tahu alamat mana yang terdaftar.
     *
     * Pola ini lolos dari kunci per-email justru karena tiap alamat dicoba
     * sedikit saja, jadi ia butuh ambangnya sendiri.
     *
     * @param  Collection<int, PlatformAuditLog>  $failures
     * @param  array<string, int>  $config
     */
    private function alertPerIp(Collection $failures, array $config, PlatformAlertService $alerts, bool $dryRun): int
    {
        $terkirim = 0;

        $perIp = $failures
            ->filter(fn (PlatformAuditLog $log) => $log->ip !== null)
            ->groupBy('ip')
            ->map(fn (Collection $group) => [
                'attempts' => $group->count(),
                'emails' => $group->pluck('meta.email')->filter()->map(fn ($e) => strtolower($e))->unique(),
            ])
            ->filter(fn (array $data) => $data['emails']->count() >= $config['per_ip_distinct_emails']);

        foreach ($perIp as $ip => $data) {
            $this->line(sprintf('Penebakan akun: %s — %d alamat berbeda dicoba', $ip, $data['emails']->count()));

            if ($dryRun) {
                $terkirim++;

                continue;
            }

            $dikirim = $alerts->send(
                key: "failed-login.ip:{$ip}",
                subject: 'Percobaan menebak akun panel platform',
                summary: sprintf(
                    'Satu alamat IP (%s) mencoba masuk dengan %d alamat surel berbeda dalam %d jam terakhir.',
                    $ip,
                    $data['emails']->count(),
                    (int) ($config['window_minutes'] / 60),
                ),
                lines: [
                    'Total '.$data['attempts'].' percobaan gagal.',
                    'Pola ini biasanya bukan penebakan kata sandi, melainkan pencarian alamat mana yang terdaftar.',
                    'Bila berlanjut, pertimbangkan memblokir IP tersebut di lapisan server.',
                ],
                meta: ['ip' => $ip, 'distinct_emails' => $data['emails']->count(), 'attempts' => $data['attempts']],
                actionUrl: url('/platform/audit-logs'),
                actionLabel: 'Lihat Jejak Audit',
            );

            $terkirim += $dikirim ? 1 : 0;
        }

        return $terkirim;
    }
}
