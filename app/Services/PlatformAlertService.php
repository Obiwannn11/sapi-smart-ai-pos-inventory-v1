<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Notifications\PlatformAlert;
use Illuminate\Support\Facades\Notification;

class PlatformAlertService
{
    /**
     * Kirim peringatan ke pemilik SaaS, kecuali peringatan sejenis baru saja
     * dikirim.
     *
     * `$key` menandai JENIS peringatan, bukan kejadiannya — mis.
     * `failed-login.email:budi@usaha.test`. Peringatan dengan kunci sama tidak
     * dikirim ulang selama masa jeda, karena satu serangan yang berlangsung
     * semalaman jika tidak akan menghasilkan satu surel tiap kali perintah
     * berjalan.
     *
     * Pengirimannya dicatat di jejak audit sebagai kejadian `sensitive`. Dua
     * kegunaan sekaligus: jejaknya ada, dan catatan itu pula yang jadi dasar
     * penghitungan jeda — jadi tidak ada dua sumber kebenaran yang bisa
     * berselisih.
     *
     * @param  list<string>  $lines
     * @param  array<string, mixed>  $meta
     */
    public function send(
        string $key,
        string $subject,
        string $summary,
        array $lines = [],
        array $meta = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): bool {
        if ($this->recentlySent($key)) {
            return false;
        }

        $notification = new PlatformAlert($subject, $summary, $lines, $actionUrl, $actionLabel);
        $recipient = config('platform-alerts.recipient');

        if ($recipient !== null) {
            Notification::route('mail', $recipient)->notify($notification);
        } else {
            // Semua pemilik platform. Staf platform sengaja tidak ikut: mereka
            // bisa saja hanya dipercaya satu modul, dan peringatan keamanan
            // memuat hal yang belum tentu boleh mereka lihat.
            $owners = PlatformUser::where('is_owner', true)->get();

            if ($owners->isEmpty()) {
                return false;
            }

            Notification::send($owners, $notification);
        }

        PlatformAuditLog::create([
            'action' => 'alert.sent',
            'severity' => PlatformAuditLog::SEVERITY_SENSITIVE,
            'meta' => $meta + ['key' => $key, 'subject' => $subject],
        ]);

        return true;
    }

    /**
     * Baris disaring di PHP, bukan di query.
     *
     * `meta` kolom JSON, dan sintaks pencarian di dalamnya berbeda antara MySQL
     * (produksi) dan SQLite (test suite). Jumlah barisnya kecil — hanya
     * peringatan dalam satu masa jeda — jadi memuatnya lebih murah daripada
     * memelihara dua dialek query untuk hal sesederhana ini.
     */
    private function recentlySent(string $key): bool
    {
        $since = now()->subMinutes((int) config('platform-alerts.cooldown_minutes'));

        return PlatformAuditLog::query()
            ->where('action', 'alert.sent')
            ->where('created_at', '>=', $since)
            ->get()
            ->contains(fn (PlatformAuditLog $log) => ($log->meta['key'] ?? null) === $key);
    }
}
