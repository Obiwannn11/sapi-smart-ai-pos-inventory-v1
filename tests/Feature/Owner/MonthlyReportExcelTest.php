<?php

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap bulanan sebagai buku kerja Excel, bukan satu CSV bersekat.
 *
 * Yang diuji di sini bukan warnanya. Yang diuji adalah tiga janji yang membuat
 * unduhannya benar-benar bisa dipakai di spreadsheet: tiap tabel berdiri di
 * lembarnya sendiri, angka tetap berupa ANGKA (bukan teks berformat yang tidak
 * bisa dijumlahkan), dan kolom yang hanya berlaku untuk sebagian tenant tetap
 * hilang untuk tenant yang lain.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Kopi Pagi']);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/**
 * Unduh buku kerjanya dan baca kembali sebagai spreadsheet.
 *
 * Isinya ditulis ke berkas sementara lebih dulu: pembaca xlsx membaca arsip
 * zip lewat jalur berkas, bukan dari string di memori.
 */
function downloadedMonthlyWorkbook(string $month): Spreadsheet
{
    $response = test()->actingAs(test()->owner)
        ->get("/owner/reports/monthly/export/excel?month={$month}");

    $response->assertStatus(200);

    $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        return IOFactory::load($path);
    } finally {
        @unlink($path);
    }
}

/**
 * Nilai di sebelah kanan sebuah label, di mana pun barisnya jatuh.
 *
 * Menambatkan assertion pada koordinat sel ("B12") membuat tiap baris yang
 * disisipkan di atasnya mematahkan uji yang tidak ada hubungannya.
 */
function excelValueBesideLabel(Worksheet $sheet, string $label, string $column = 'B'): mixed
{
    foreach (range(1, $sheet->getHighestRow()) as $row) {
        if ((string) $sheet->getCell("A{$row}")->getValue() === $label) {
            return $sheet->getCell("{$column}{$row}")->getValue();
        }
    }

    return null;
}

/**
 * Baris kepala sebuah tabel — dicari lewat sel pertamanya.
 *
 * @return array<int, string>
 */
function excelHeadingRow(Worksheet $sheet, string $firstHeading): array
{
    foreach (range(1, $sheet->getHighestRow()) as $row) {
        if ((string) $sheet->getCell("A{$row}")->getValue() === $firstHeading) {
            return array_map(
                fn ($cell) => (string) $cell,
                $sheet->rangeToArray("A{$row}:".$sheet->getHighestColumn().$row)[0],
            );
        }
    }

    return [];
}

/**
 * Satu penjualan sederhana pada tanggal tertentu.
 */
function monthlyExcelSale(float $total, string $occurredAt): Transaction
{
    return Transaction::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $total,
        'occurred_at' => $occurredAt,
    ]);
}

test('unduhan excel dinamai dan bertipe xlsx', function () {
    $response = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export/excel?month=2026-05');

    $response->assertStatus(200)
        ->assertHeader('content-disposition', 'attachment; filename=laporan-bulanan-2026-05.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('tiap tabel berdiri di lembarnya sendiri', function () {
    monthlyExcelSale(65000, '2026-05-12 10:00:00');

    $book = downloadedMonthlyWorkbook('2026-05');

    expect($book->getSheetNames())->toBe([
        'Ringkasan',
        'Rincian Harian',
        'Metode Pembayaran',
        'Produk Terlaris',
    ]);

    // Lembar pertama yang aktif saat filenya dibuka, bukan lembar terakhir
    // yang kebetulan baru saja ditulis.
    expect($book->getActiveSheetIndex())->toBe(0);
});

test('uang ditulis sebagai angka, bukan teks berformat', function () {
    monthlyExcelSale(65000, '2026-05-12 10:00:00');

    $sheet = downloadedMonthlyWorkbook('2026-05')->getSheetByName('Ringkasan');
    $revenue = excelValueBesideLabel($sheet, 'Omzet (dibayar pelanggan)');

    // Inti seluruh perubahan ini: "Rp 65.000" sebagai teks terbaca sama oleh
    // mata dan tidak bisa dijumlahkan sama sekali oleh spreadsheet.
    expect($revenue)->toBeNumeric()
        ->and((float) $revenue)->toBe(65000.0);
});

test('rincian harian memuat satu baris per hari kalender, ditutup baris total', function () {
    monthlyExcelSale(65000, '2026-05-12 10:00:00');
    monthlyExcelSale(35000, '2026-05-20 10:00:00');

    $sheet = downloadedMonthlyWorkbook('2026-05')->getSheetByName('Rincian Harian');
    $heading = excelHeadingRow($sheet, 'Tanggal');

    expect($heading)->toBe(['Tanggal', 'Hari', 'Transaksi', 'Omzet', 'Void']);

    // Mei punya 31 hari; hari tutup tetap punya barisnya sendiri, lalu satu
    // baris TOTAL di bawahnya.
    $headingRow = 4;
    $totalRow = $headingRow + 31 + 1;

    expect((string) $sheet->getCell("A{$totalRow}")->getValue())->toBe('TOTAL')
        ->and((float) $sheet->getCell("D{$totalRow}")->getValue())->toBe(100000.0)
        ->and((int) $sheet->getCell("C{$totalRow}")->getValue())->toBe(2);
});

test('tanggal di rincian harian adalah tanggal, bukan teks', function () {
    monthlyExcelSale(65000, '2026-05-12 10:00:00');

    $sheet = downloadedMonthlyWorkbook('2026-05')->getSheetByName('Rincian Harian');

    // Baris pertama data = 1 Mei. Sebagai teks ia akan terurut secara alfabet
    // dan tidak bisa di-pivot per minggu atau per kuartal.
    //
    // Yang diperiksa nilainya, bukan tampilannya: nama bulan pada format
    // `dd mmm yyyy` dirender oleh Excel menurut locale pembacanya, dan
    // pemformat bawaan PhpSpreadsheet selalu menjawabnya dalam bahasa Inggris.
    // Nama hari di kolom sebelahnya memang ditulis sendiri, dan itu yang
    // menjamin lembarnya tetap berbahasa Indonesia di mesin mana pun.
    expect($sheet->getCell('A5')->getValue())->toBeNumeric()
        ->and(ExcelDate::excelToDateTimeObject($sheet->getCell('A5')->getValue())->format('Y-m-d'))
        ->toBe('2026-05-01')
        ->and($sheet->getStyle('A5')->getNumberFormat()->getFormatCode())->toBe('dd mmm yyyy')
        ->and((string) $sheet->getCell('B5')->getValue())->toBe('Jumat');
});

test('kolom pajak dan biaya layanan hanya lahir untuk tenant yang memungut', function () {
    monthlyExcelSale(65000, '2026-05-12 10:00:00');

    $quiet = downloadedMonthlyWorkbook('2026-05')->getSheetByName('Rincian Harian');
    expect(excelHeadingRow($quiet, 'Tanggal'))->not->toContain('PPN');

    $this->tenant->update([
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PPN',
    ]);
    // `actingAs` memakai instance user yang sama sepanjang test, dan relasi
    // tenant-nya sudah termuat dari permintaan di atas.
    $this->owner->refresh();

    Transaction::factory()->taxed(100000)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-06-12 10:00:00',
    ]);

    $taxed = downloadedMonthlyWorkbook('2026-06');

    expect(excelHeadingRow($taxed->getSheetByName('Rincian Harian'), 'Tanggal'))->toContain('PPN');
    expect(excelValueBesideLabel($taxed->getSheetByName('Ringkasan'), 'PPN terpungut'))
        ->toEqual(11000);
});

test('tipe pembayaran ditulis seperti yang dibaca owner di layar', function () {
    $method = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Tunai Kasir',
        'type' => 'cash',
    ]);

    $sale = monthlyExcelSale(39000, '2026-05-12 10:00:00');
    TransactionPayment::factory()->create([
        'transaction_id' => $sale->id,
        'payment_method_id' => $method->id,
        'amount' => 39000,
    ]);

    $sheet = downloadedMonthlyWorkbook('2026-05')->getSheetByName('Metode Pembayaran');

    expect((string) $sheet->getCell('A5')->getValue())->toBe('Tunai Kasir')
        // `qris_static` dan `cash` adalah nama kolom database yang bocor ke
        // laporan; yang dikenali pemilik toko adalah "Tunai".
        ->and((string) $sheet->getCell('B5')->getValue())->toBe('Tunai')
        ->and((float) $sheet->getCell('C5')->getValue())->toBe(39000.0)
        // Porsi ditulis sebagai pecahan dengan format persen, bukan string "100%".
        ->and((float) $sheet->getCell('D5')->getValue())->toBe(1.0);
});

test('produk terlaris memuat tiap varian di bawah nama produknya sendiri', function () {
    $latte = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cafe Latte']);
    $aren = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu Gula Aren']);

    $latteHot = ProductVariant::factory()->create(['product_id' => $latte->id, 'name' => 'Hot']);
    $arenHot = ProductVariant::factory()->create(['product_id' => $aren->id, 'name' => 'Hot']);

    monthlyExcelSale(80000, '2026-05-04 09:00:00')->items()->create([
        'product_variant_id' => $latteHot->id,
        'variant_name' => 'Hot',
        'qty' => 4,
        'unit_price' => 20000,
        'subtotal' => 80000,
    ]);
    monthlyExcelSale(132000, '2026-05-06 09:00:00')->items()->create([
        'product_variant_id' => $arenHot->id,
        'variant_name' => 'Hot',
        'qty' => 6,
        'unit_price' => 22000,
        'subtotal' => 132000,
    ]);

    $sheet = downloadedMonthlyWorkbook('2026-05')->getSheetByName('Produk Terlaris');

    // `formatData: false` — yang diperiksa nilai selnya, bukan "Rp 132.000"
    // yang dirender di atasnya.
    expect($sheet->rangeToArray('A5:E6', formatData: false))->toBe([
        [1, 'Kopi Susu Gula Aren', 'Hot', 6, 132000.0],
        [2, 'Cafe Latte', 'Hot', 4, 80000.0],
    ]);

    expect((string) $sheet->getCell('A7')->getValue())->toBe('TOTAL 10 PRODUK TERATAS')
        ->and((int) $sheet->getCell('D7')->getValue())->toBe(10);
});

test('bulan tanpa penjualan tetap menghasilkan buku kerja yang utuh', function () {
    $book = downloadedMonthlyWorkbook('2026-05');

    expect($book->getSheetNames())->toHaveCount(4)
        ->and((float) excelValueBesideLabel($book->getSheetByName('Ringkasan'), 'Omzet (dibayar pelanggan)'))->toBe(0.0);

    // Tabel kosong mengatakan dirinya kosong, bukan memperlihatkan kepala tabel
    // yang menggantung di atas ruang putih.
    expect((string) $book->getSheetByName('Produk Terlaris')->getCell('A5')->getValue())
        ->toBe('Tidak ada penjualan pada periode ini.');
});
