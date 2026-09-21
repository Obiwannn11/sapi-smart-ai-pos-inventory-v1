<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Mengenali pendaftaran yang berulang dari sumber yang sama.
 *
 * Menandai, bukan memblokir. Satu IP publik bisa dipakai bersama satu kompleks
 * pertokoan atau satu jaringan seluler kota kecil; memblokir otomatis akan
 * menjegal warung sebelah yang tidak melakukan kesalahan apa pun. Menandai
 * membuat orang yang memutuskan, memblokir membuat mesin memutuskan atas hal
 * yang tidak cukup ia ketahui.
 *
 * Penghalang utamanya bukan di sini, melainkan verifikasi surel: tanpa alamat
 * yang benar-benar bisa dibuka, akun barunya tidak bisa dipakai untuk apa pun.
 * Penandaan ini lapisan kedua, untuk pola yang lolos dari lapisan pertama.
 */
class SignupGuardService
{
    public function __construct(private readonly PlatformAlertService $alerts) {}

    /**
     * Catat asal pendaftaran, dan tandai bila dari IP itu sudah terlalu banyak
     * tenant lahir belakangan ini.
     */
    public function record(Tenant $tenant, ?string $ip): void
    {
        $tenant->update(['signup_ip' => $ip]);

        if ($ip === null) {
            return;
        }

        $config = config('platform-alerts.signup');
        $since = now()->subHours((int) $config['window_hours']);

        $serumpun = Tenant::query()
            ->where('signup_ip', $ip)
            ->where('created_at', '>=', $since)
            ->get();

        if ($serumpun->count() <= (int) $config['max_per_ip']) {
            return;
        }

        $tenant->update([
            'flagged_at' => now(),
            'flag_reason' => sprintf(
                '%d pendaftaran dari IP %s dalam %d jam terakhir.',
                $serumpun->count(),
                $ip,
                (int) $config['window_hours'],
            ),
        ]);

        $this->alerts->send(
            // Berkunci pada IP, bukan pada tenant: yang perlu diketahui adalah
            // POLA-nya. Satu surel per gelombang jauh lebih berguna daripada
            // satu surel per pendaftaran.
            key: "signup.ip:{$ip}",
            subject: 'Pendaftaran berulang dari satu alamat IP',
            summary: sprintf(
                'Ada %d pendaftaran usaha baru dari IP %s dalam %d jam terakhir.',
                $serumpun->count(),
                $ip,
                (int) $config['window_hours'],
            ),
            lines: [
                'Usaha yang terdaftar: '.$serumpun->pluck('name')->take(6)->implode(', ').'.',
                'Tenant-tenant ini DITANDAI, bukan diblokir — satu IP publik bisa dipakai bersama beberapa usaha yang sah.',
                'Semuanya tetap harus memverifikasi alamat emailnya sebelum bisa dipakai.',
            ],
            meta: ['ip' => $ip, 'tenant_count' => $serumpun->count()],
            actionUrl: url('/platform/tenants'),
            actionLabel: 'Lihat Daftar Tenant',
        );
    }
}
