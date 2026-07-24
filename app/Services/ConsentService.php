<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\User;
use InvalidArgumentException;

class ConsentService
{
    /**
     * Isi dan versi dokumen persetujuan untuk sebuah jalur harga.
     *
     * @return array{version: string, body: string}
     */
    public function document(string $type): array
    {
        $config = config("subscription.consents.{$type}");

        if ($config === null) {
            throw new InvalidArgumentException("Dokumen persetujuan '{$type}' tidak dikenal.");
        }

        $path = resource_path('consents/'.$config['file']);

        if (! is_file($path)) {
            throw new InvalidArgumentException("Berkas persetujuan '{$config['file']}' tidak ditemukan.");
        }

        return [
            'version' => (string) $config['version'],
            'body' => file_get_contents($path),
        ];
    }

    public function currentVersion(string $type): string
    {
        return (string) config("subscription.consents.{$type}.version");
    }

    /**
     * Persetujuan aktif terakhir tenant untuk jalur ini, bila ada.
     */
    public function latestFor(Tenant $tenant, string $type): ?TenantConsent
    {
        return $tenant->consents()
            ->active()
            ->where('type', $type)
            ->latest('agreed_at')
            ->first();
    }

    /**
     * Sudah menyetujui versi yang BERLAKU SEKARANG — bukan sekadar pernah
     * menyetujui versi mana pun. Teks yang berubah harus disetujui ulang,
     * kalau tidak persetujuannya menunjuk kalimat yang tak pernah dibaca.
     */
    public function hasAgreedToCurrent(Tenant $tenant, string $type): bool
    {
        return $this->latestFor($tenant, $type)?->version === $this->currentVersion($type);
    }

    /**
     * Catat persetujuan. Baris baru selalu, tidak pernah menimpa yang lama —
     * riwayat persetujuan yang bisa disunting bukan bukti.
     */
    public function record(Tenant $tenant, User $user, string $type, ?string $ip): TenantConsent
    {
        return $tenant->consents()->create([
            'user_id' => $user->id,
            'type' => $type,
            'version' => $this->currentVersion($type),
            'agreed_at' => now(),
            'ip' => $ip,
        ]);
    }
}
