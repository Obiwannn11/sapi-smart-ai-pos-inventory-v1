<?php

namespace Database\Seeders;

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashDrawerReconciliation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tiga pemicu `[BL-028]` Tahap B, disemai ke tenant demo Kopi Nusantara
 * (`owner@sapi.test`) supaya bisa diperiksa dari layar, bukan hanya dari test.
 *
 * Dijalankan terpisah: `php artisan db:seed --class=CashDrawerScenarioSeeder`.
 * Membutuhkan dua kasir di tenant itu; yang dipakai dua kasir pertama menurut
 * id, bukan menurut email, karena akun kasir kedua di basis data lokal tidak
 * selalu `kasir2@sapi.test`.
 *
 * Semua skenario jatuh pada hari H = tiga hari lalu:
 *
 *   A: laci bersamaan   kasir A 08:00–16:00, bersamaan dengan shift pagi B
 *   B: shift pagi       kasir B 08:00–12:00          (pemicu 1: kasir kedua)
 *   B: shift sore       kasir B 13:00–20:00          (pemicu 2: dua sesi sehari)
 *   B: ditutup paksa    kasir B H 21:00 – H+1 21:00  (pemicu 3: tutup paksa)
 *
 * Plus tagihan terbuka yang DIBUAT kasir A pukul 11:00 dan DILUNASI kasir B di
 * shift sore — turunan lama `user_id` + jendela akan menaruhnya di laci A — dan
 * satu penjualan B sesudah sesinya ditutup paksa, yang tidak jatuh ke laci mana
 * pun.
 *
 * Tanpa `stock_movements`: yang diperagakan di sini angka laci, dan skenario
 * yang diam-diam mengurangi stok katalog demo akan membuat peragaan stok
 * berikutnya tampak salah.
 *
 * Aman dijalankan ulang: bila laci bertanda {@see self::MARKER} sudah ada,
 * seeder berhenti tanpa menulis apa pun.
 */
class CashDrawerScenarioSeeder extends Seeder
{
    public const MARKER = '[Skenario BL-028]';

    private int $sequence = 0;

    public function run(CashDrawerReconciliation $reconciliation): void
    {
        $tenant = Tenant::where('slug', 'kopi-nusantara')->first();

        if (! $tenant) {
            $this->command?->error('Tenant kopi-nusantara tidak ada. Jalankan DatabaseSeeder dulu.');

            return;
        }

        if (CashDrawer::where('tenant_id', $tenant->id)->where('notes', 'like', self::MARKER.'%')->exists()) {
            $this->command?->info('Skenario BL-028 sudah pernah disemai; tidak ada yang ditulis.');

            return;
        }

        $cashiers = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->orderBy('id')->limit(2)->get();

        if ($cashiers->count() < 2) {
            $this->command?->error('Skenario BL-028 membutuhkan dua kasir di tenant kopi-nusantara.');

            return;
        }

        [$kasirA, $kasirB] = [$cashiers[0], $cashiers[1]];

        $cash = PaymentMethod::where('tenant_id', $tenant->id)->where('type', 'cash')->firstOrFail();
        $qris = PaymentMethod::where('tenant_id', $tenant->id)->where('type', 'like', 'qris%')->firstOrFail();
        $variant = ProductVariant::whereHas('product', fn ($query) => $query->where('tenant_id', $tenant->id))->firstOrFail();

        $day = Carbon::now()->subDays(3)->startOfDay();
        $at = fn (int $hour, int $addDays = 0): Carbon => $day->copy()->addDays($addDays)->setTime($hour, 0);

        $kasirBBusy = CashDrawer::where('user_id', $kasirB->id)
            ->where('opened_at', '<', $at(0, 2))
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>', $day))
            ->exists();

        if ($kasirBBusy) {
            $this->command?->error("{$kasirB->email} sudah punya sesi kas pada {$day->toDateString()}; skenario tidak disemai.");

            return;
        }

        DB::transaction(function () use ($reconciliation, $tenant, $kasirA, $kasirB, $cash, $qris, $variant, $at) {
            // Kasir A di basis data lokal bisa saja masih memegang sesi lama
            // yang melingkupi hari H. Sesi itu dipakai apa adanya dan angkanya
            // tidak disentuh — yang diperagakan adalah laci B tidak memungut
            // uang A, bukan angka laci A sendiri.
            $drawerA = CashDrawer::coveringAt($kasirA, $at(8));
            $ownsDrawerA = $drawerA === null;
            $drawerA ??= $this->drawer($tenant, $kasirA, 300_000, $at(8), $at(16), 'A: laci bersamaan');

            $morningB = $this->drawer($tenant, $kasirB, 200_000, $at(8), $at(12), 'B: shift pagi');
            $eveningB = $this->drawer($tenant, $kasirB, 250_000, $at(13), $at(20), 'B: shift sore');
            $forcedB = $this->drawer($tenant, $kasirB, 150_000, $at(21), $at(21, 1), 'B: ditutup paksa', closedBySystem: true);

            $this->sale($kasirA, $drawerA, $variant, $cash, 150_000, tendered: 200_000, createdAt: $at(9));
            $this->sale($kasirB, $morningB, $variant, $cash, 80_000, tendered: 80_000, createdAt: $at(10));
            $this->sale($kasirB, $morningB, $variant, $qris, 45_000, tendered: 45_000, createdAt: $at(11));
            $this->sale($kasirB, $eveningB, $variant, $cash, 120_000, tendered: 120_000, createdAt: $at(14));

            // Dibuat A di tengah shiftnya, dilunasi B di shift sore.
            $this->sale($kasirA, $eveningB, $variant, $cash, 95_000, tendered: 100_000, createdAt: $at(11), paidAt: $at(15));

            $this->sale($kasirB, $forcedB, $variant, $cash, 60_000, tendered: 60_000, createdAt: $at(22));
            $this->sale($kasirB, null, $variant, $cash, 35_000, tendered: 35_000, createdAt: $at(22, 1));

            if ($ownsDrawerA) {
                $this->count($reconciliation, $drawerA, shortBy: 0);
            }

            $this->count($reconciliation, $morningB, shortBy: 0);
            $this->count($reconciliation, $eveningB, shortBy: 10_000);

            $forcedB->update(['expected_amount' => $reconciliation->for($forcedB)['expected_amount']]);
        });

        $this->command?->info("Skenario BL-028 disemai pada {$day->toDateString()} untuk {$kasirA->email} dan {$kasirB->email}.");
    }

    private function drawer(
        Tenant $tenant,
        User $user,
        int $opening,
        Carbon $openedAt,
        Carbon $closedAt,
        string $label,
        bool $closedBySystem = false,
    ): CashDrawer {
        $notes = self::MARKER.' '.$label;

        if ($closedBySystem) {
            $notes .= "\n".sprintf(
                'Ditutup otomatis oleh sistem setelah lewat %d jam. Uang fisik tidak pernah dihitung.',
                CashDrawer::MAX_SESSION_HOURS,
            );
        }

        return CashDrawer::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'opening_amount' => $opening,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'closed_by_system' => $closedBySystem,
            'notes' => $notes,
        ]);
    }

    /**
     * Satu penjualan selesai. `$paidAt` membuatnya tagihan terbuka yang
     * dilunasi belakangan: tanggal efektifnya tetap saat ia dibuat.
     */
    private function sale(
        User $user,
        ?CashDrawer $drawer,
        ProductVariant $variant,
        PaymentMethod $method,
        int $total,
        int $tendered,
        Carbon $createdAt,
        ?Carbon $paidAt = null,
    ): void {
        $paidAt ??= $createdAt;
        $code = 'TRX-'.$createdAt->format('Ymd').'-S'.str_pad((string) ++$this->sequence, 3, '0', STR_PAD_LEFT);

        $transactionId = DB::table('transactions')->insertGetId([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'cash_drawer_id' => $drawer?->id,
            'code' => $code,
            'status' => 'completed',
            'subtotal_amount' => $total,
            'tax_amount' => 0,
            'total_amount' => $total,
            'change_amount' => $tendered - $total,
            'source' => 'pos',
            'order_type' => 'dine_in',
            'created_at' => $createdAt,
            'updated_at' => $paidAt,
        ]);

        DB::table('transaction_items')->insert([
            'transaction_id' => $transactionId,
            'product_variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'qty' => 1,
            'unit_price' => $total,
            'subtotal' => $total,
        ]);

        DB::table('transaction_payments')->insert([
            'transaction_id' => $transactionId,
            'payment_method_id' => $method->id,
            'amount' => $tendered,
            'created_at' => $paidAt,
        ]);
    }

    /** Tutup kas oleh kasir: angka seharusnya dibekukan, uang fisik dicatat. */
    private function count(CashDrawerReconciliation $reconciliation, CashDrawer $drawer, int $shortBy): void
    {
        $expected = $reconciliation->for($drawer)['expected_amount'];

        $drawer->update([
            'expected_amount' => $expected,
            'closing_amount' => $expected - $shortBy,
            'difference' => -$shortBy,
        ]);
    }
}
