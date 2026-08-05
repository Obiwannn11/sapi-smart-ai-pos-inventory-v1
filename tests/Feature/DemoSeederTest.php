<?php

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CafeStudyCaseSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Satu transaksi penanda pada sebuah tanggal, semurah mungkin.
 *
 * Dipakai untuk "menempati" hari-hari yang seharusnya dilewati seeder, supaya
 * pengujian tidak perlu menunggu 90 hari penjualan sungguhan disemai hanya
 * untuk membuktikan bahwa hari yang sudah terisi memang tidak disentuh.
 */
function penandaTransaksi(Tenant $tenant, User $kasir, Carbon $day): void
{
    DB::table('transactions')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $kasir->id,
        'code' => 'PENANDA-'.$day->format('Ymd'),
        'status' => 'completed',
        'total_amount' => 10000,
        'change_amount' => 0,
        'source' => 'pos',
        'order_type' => 'dine_in',
        'created_at' => $day->copy()->setTime(9, 0),
        'updated_at' => $day->copy()->setTime(9, 0),
    ]);
}

function jumlahTransaksi(Tenant $tenant): int
{
    return DB::table('transactions')->where('tenant_id', $tenant->id)->count();
}

/**
 * Cacah transaksi pada satu tanggal penjualan.
 */
function transaksiPadaHari(Tenant $tenant, Carbon $day): int
{
    return DB::table('transactions')
        ->where('tenant_id', $tenant->id)
        ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
        ->count();
}

it('hanya menyemai hari yang belum punya transaksi', function () {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::where('slug', 'kopi-nusantara')->firstOrFail();
    $kasir = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

    // Seluruh jendela 90 hari ditempati, kecuali tiga hari terakhir.
    for ($daysAgo = 89; $daysAgo >= 3; $daysAgo--) {
        penandaTransaksi($tenant, $kasir, Carbon::today()->subDays($daysAgo));
    }

    $sebelum = jumlahTransaksi($tenant);
    $this->seed(DemoTransactionSeeder::class);

    expect(jumlahTransaksi($tenant))->toBeGreaterThan($sebelum);

    foreach ([2, 1, 0] as $daysAgo) {
        expect(transaksiPadaHari($tenant, Carbon::today()->subDays($daysAgo)))->toBeGreaterThan(1);
    }

    // Hari yang sudah terisi tetap berisi persis satu penanda.
    expect(transaksiPadaHari($tenant, Carbon::today()->subDays(10)))->toBe(1);
});

it('tidak menggandakan omzet saat dijalankan dua kali', function () {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::where('slug', 'kopi-nusantara')->firstOrFail();
    $kasir = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

    for ($daysAgo = 89; $daysAgo >= 1; $daysAgo--) {
        penandaTransaksi($tenant, $kasir, Carbon::today()->subDays($daysAgo));
    }

    $this->seed(DemoTransactionSeeder::class);
    $setelahSekali = jumlahTransaksi($tenant);
    $omzetSekali = DB::table('transactions')->where('tenant_id', $tenant->id)->sum('total_amount');

    $this->seed(DemoTransactionSeeder::class);

    expect(jumlahTransaksi($tenant))->toBe($setelahSekali);
    expect(DB::table('transactions')->where('tenant_id', $tenant->id)->sum('total_amount'))
        ->toEqual($omzetSekali);
});

it('menambal jarak sampai hari ini', function () {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::where('slug', 'kopi-nusantara')->firstOrFail();
    $kasir = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

    for ($daysAgo = 89; $daysAgo >= 5; $daysAgo--) {
        penandaTransaksi($tenant, $kasir, Carbon::today()->subDays($daysAgo));
    }

    $this->seed(DemoTransactionSeeder::class);

    expect(transaksiPadaHari($tenant, Carbon::today()))->toBeGreaterThan(0);
});

it('tidak menumpuk restock bulanan Kopi Story tiap kali dijalankan', function () {
    $tenant = Tenant::factory()->create(['slug' => 'kopi-story', 'name' => 'Kopi Story']);
    $kasir = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'kasir@kopistory.test',
        'role' => 'cashier',
    ]);

    // Seluruh kalender penjualannya ditempati, jadi yang diuji murni restoknya.
    for ($day = Carbon::parse('2026-01-01'); $day->lte(Carbon::today()); $day->addDay()) {
        penandaTransaksi($tenant, $kasir, $day->copy());
    }

    $this->seed(CafeStudyCaseSeeder::class);

    $restock = fn () => DB::table('stock_movements')
        ->where('tenant_id', $tenant->id)->where('type', 'restock')->count();

    $setelahSekali = $restock();
    $transaksiSekali = jumlahTransaksi($tenant);
    expect($setelahSekali)->toBeGreaterThan(0);

    $this->seed(CafeStudyCaseSeeder::class);

    expect($restock())->toBe($setelahSekali);
    expect(jumlahTransaksi($tenant))->toBe($transaksiSekali);
});
