<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TransactionPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Foto bukti bayar non-tunai di kasir.
 *
 * **Kenapa fotonya diunggah SEBELUM penjualannya disimpan, bukan bersamanya.**
 * Checkout POS mengirim JSON bersarang — item, modifier, pembayaran, kejadian
 * upsell. Menyelipkan berkas ke dalamnya memaksa seluruh payload berpindah ke
 * `multipart/form-data`, dan di sana setiap angka berubah jadi string dan
 * setiap boolean jadi "1"/"0". Menukar encoding seluruh jalur checkout demi
 * satu foto berarti mempertaruhkan validasi yang menjaga uang demi hal yang
 * jauh lebih sepele daripada uang.
 *
 * Jadi alurnya dua langkah: foto diunggah sendiri dan menghasilkan TOKEN, lalu
 * checkout mengirim tokennya sebagai string biasa. Efek sampingnya justru yang
 * diminta `[BL-075]` bagian offline butir 4 — unggahan foto jadi langkah yang
 * bisa diulang sendiri tanpa mengirim ulang penjualannya.
 *
 * **Berkas yang belum diklaim adalah sampah, dan itu harus diurus.** Kasir yang
 * memotret lalu membatalkan modal meninggalkan berkas di `pending/`. Yang
 * membersihkannya `payment-proofs:prune-unclaimed`, dan ia HANYA menyentuh
 * `pending/` — foto yang sudah melekat pada pembayaran tidak pernah ia lihat.
 * Retensi foto yang sudah diklaim SUDAH diputuskan: tanpa batas untuk
 * sekarang, tanpa pembersihan otomatis (keputusan pemilik 2026-08-19 di
 * `[BL-075]`). Jadi perintah di atas adalah kebersihan disk, BUKAN kebijakan
 * retensi — keduanya sengaja tidak digabung.
 */
class PaymentProofService
{
    /** Akar direktori, di disk privat yang sama dengan bukti transfer. */
    private const ROOT = 'payment-proofs';

    public function __construct(
        private readonly ProofFileService $files,
    ) {}

    /**
     * Simpan foto yang belum melekat pada pembayaran mana pun.
     *
     * @return string Token — dikirim client kembali saat checkout.
     */
    public function storePending(UploadedFile $file, int $tenantId): string
    {
        $path = $this->files->store($file, $this->pendingDirectory($tenantId));

        return $this->tokenFrom($path);
    }

    /**
     * Pindahkan foto tertunda menjadi milik sebuah pembayaran.
     *
     * @return string|null Path akhir, atau null bila tokennya tidak sah atau
     *                     berkasnya sudah tidak ada. TIDAK melempar: sebuah
     *                     penjualan yang sudah terjadi tidak boleh dibatalkan
     *                     karena fotonya hilang.
     */
    public function claim(?string $token, int $tenantId): ?string
    {
        if (! $this->isValidToken($token)) {
            return null;
        }

        $pending = $this->pendingDirectory($tenantId)."/{$token}.webp";
        $final = $this->directory($tenantId)."/{$token}.webp";

        $disk = $this->files->disk();

        if (! $disk->exists($pending)) {
            return null;
        }

        $disk->move($pending, $final);

        $pendingThumb = $this->files->thumbPath($pending);

        if ($disk->exists($pendingThumb)) {
            $disk->move($pendingThumb, $this->files->thumbPath($final));
        }

        return $final;
    }

    /**
     * Apakah token ini menunjuk foto tertunda yang benar-benar ada.
     *
     * Dipakai VALIDASI, sebelum penjualannya tersimpan — supaya "wajib berfoto"
     * ditegakkan atas berkas yang nyata, bukan atas string yang dikarang
     * client.
     */
    public function pendingExists(?string $token, int $tenantId): bool
    {
        if (! $this->isValidToken($token)) {
            return false;
        }

        return $this->files->disk()->exists(
            $this->pendingDirectory($tenantId)."/{$token}.webp"
        );
    }

    /**
     * Path rendition yang diminta untuk sebuah pembayaran.
     */
    public function pathFor(TransactionPayment $payment, string $size): ?string
    {
        if (! $payment->proof_path) {
            return null;
        }

        return $this->files->pathFor($payment->proof_path, $size);
    }

    /**
     * Pembayaran non-tunai mana yang belum berfoto, bila tokonya mewajibkan
     * ([BL-075]).
     *
     * Hidup di service, bukan di salah satu FormRequest, karena ada DUA jalur
     * online yang membuat pembayaran — checkout dan bayar open bill — dan
     * aturan wajib yang ditulis dua kali adalah aturan yang suatu hari akan
     * berbeda di satu tempat. Jalur ketiga (sinkronisasi offline) tidak
     * memanggilnya: ia cash-only, jadi tidak pernah ada yang bisa wajib.
     *
     * **"Non-tunai" diturunkan dari `payment_methods.type`, bukan dari daftar
     * baru.** Enum-nya sudah membedakan `cash` dari sisanya sejak awal; tipe
     * yang ditambahkan nanti otomatis ikut terhitung non-tunai — jawaban yang
     * memang benar.
     *
     * **Tokennya diperiksa benar-benar menunjuk berkas yang ada.** Tanpa itu,
     * "wajib berfoto" bisa dipenuhi dengan mengarang UUID, dan pembayarannya
     * tersimpan tanpa bukti apa pun — persis keadaan yang aturan ini cegah.
     *
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, string> Indeks baris pembayaran => pesan galatnya.
     */
    public function missingProofs(array $payments, ?Tenant $tenant): array
    {
        if (! $tenant?->hasFeature('payment_proof')) {
            return [];
        }

        $methods = PaymentMethod::where('tenant_id', $tenant->id)
            ->whereIn('id', collect($payments)->pluck('payment_method_id')->filter())
            ->get()
            ->keyBy('id');

        $missing = [];

        foreach ($payments as $index => $payment) {
            $method = $methods->get($payment['payment_method_id'] ?? null);

            if (! $method || $method->isCash()) {
                continue;
            }

            if (! $this->pendingExists($payment['proof_token'] ?? null, $tenant->id)) {
                $missing[$index] = "Foto bukti bayar wajib untuk {$method->name}.";
            }
        }

        return $missing;
    }

    public function files(): ProofFileService
    {
        return $this->files;
    }

    public function pendingDirectory(int $tenantId): string
    {
        return self::ROOT."/{$tenantId}/pending";
    }

    public function directory(int $tenantId): string
    {
        return self::ROOT."/{$tenantId}";
    }

    /**
     * Token adalah nama berkas tanpa ekstensinya — sebuah UUID yang dibuat
     * SERVER. Bentuknya diperiksa sebelum dipakai menyusun path: token yang
     * datang dari client dan langsung disambung jadi path adalah jalan masuk
     * untuk `../`.
     */
    private function isValidToken(?string $token): bool
    {
        return $token !== null && Str::isUuid($token);
    }

    private function tokenFrom(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
}
