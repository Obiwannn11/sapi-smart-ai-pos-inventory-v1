<?php

use App\Models\DiscountRule;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * Struk menampilkan potongan, bukan hanya harga yang sudah dipotong
 * ([BL-103] butir 2a).
 *
 * Sebelum ini, Teh Manis yang didiskon dari Rp 10.000 ke Rp 7.000 tercetak
 * "2 x Rp 7.000" — angka yang tidak ada di papan menu, tanpa satu kata pun
 * tentang sebabnya. Pemilik meminta struknya transparan supaya pelanggan
 * tidak kaget.
 *
 * Struk termal dibangun di `resources/js/services/escpos.js`, bukan di server,
 * jadi test ini menjalankan pembangun itu sungguhan lewat node dengan objek
 * transaksi hasil checkout sungguhan. Struk layar (`ReceiptModal.vue`) membaca
 * helper yang sama, `resources/js/support/discount.js`.
 */

/**
 * @return array{cashier: User, owner: User, tea: ProductVariant, coffee: ProductVariant, cash: PaymentMethod}
 */
function receiptDiscountSale(): array
{
    $tenant = Tenant::factory()->active()->create(['min_margin_percent' => 10]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    return [
        'cashier' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']),
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'tea' => ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Teh Manis', 'price' => 10000, 'cost_price' => 5000, 'stock' => 50, 'expiry_date' => null,
        ]),
        'coffee' => ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Kopi', 'price' => 15000, 'cost_price' => 5000, 'stock' => 50, 'expiry_date' => null,
        ]),
        'cash' => PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']),
    ];
}

/**
 * Transaksi dalam bentuk yang sama dengan `lastTransaction` yang dikirim
 * `POSController` ke struk.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function receiptDiscountCheckout(User $actor, PaymentMethod $cash, array $items, float $paid): array
{
    actingAs($actor);

    $transaction = app(TransactionService::class)->checkout([
        'items' => array_map(fn (array $item) => $item + ['modifiers' => []], $items),
        'payments' => [['payment_method_id' => $cash->id, 'amount' => $paid]],
    ]);

    return Transaction::with(['items.modifiers', 'payments.paymentMethod', 'user:id,name'])
        ->findOrFail($transaction->id)
        ->toArray();
}

/**
 * Jalankan `buildReceipt()` dan kembalikan teks struknya per baris, tanpa byte
 * perintah printer.
 *
 * @param  array<string, mixed>  $transaction
 * @return list<string>
 */
function renderThermalReceiptLines(array $transaction, int $paperWidth = 58): array
{
    try {
        $hasNode = Process::run(['node', '--version'])->successful();
    } catch (Throwable) {
        $hasNode = false;
    }

    if (! $hasNode) {
        test()->markTestSkipped('node tidak tersedia, pembangun struk termal tidak bisa dijalankan.');
    }

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'receipt-discount-'.Str::random(8);
    File::ensureDirectoryExists($dir);

    // Alias `@/` milik Vite diterjemahkan ke `resources/js`, supaya yang
    // dijalankan adalah berkas aslinya, bukan salinan yang bisa menyimpang.
    File::put($dir.DIRECTORY_SEPARATOR.'hooks.mjs', <<<'JS'
        import { pathToFileURL } from 'node:url';
        import { extname, join } from 'node:path';

        export async function resolve(specifier, context, nextResolve) {
            if (specifier.startsWith('@/')) {
                const target = join(process.env.JS_ROOT, specifier.slice(2));

                return nextResolve(pathToFileURL(extname(target) ? target : `${target}.js`).href, context);
            }

            return nextResolve(specifier, context);
        }
        JS);

    $script = $dir.DIRECTORY_SEPARATOR.'run.mjs';
    File::put($script, <<<'JS'
        import { register } from 'node:module';
        import { readFileSync } from 'node:fs';
        import { pathToFileURL } from 'node:url';

        register(pathToFileURL(process.env.HOOKS).href);

        const { buildReceipt } = await import(pathToFileURL(process.env.ESCPOS).href);
        const { transaction, paperWidth } = JSON.parse(readFileSync(0, 'utf8'));

        const text = Buffer.from(buildReceipt(transaction, { paperWidth, header: 'Toko Uji' }))
            .toString('latin1')
            .replace(/\x1b@|\x1b[aE][\x00-\x02]|\x1d![\x00\x11]|\x1dVB\x00/g, '');

        process.stdout.write(JSON.stringify(text.split('\n')));
        JS);

    try {
        $result = Process::env([
            'HOOKS' => $dir.DIRECTORY_SEPARATOR.'hooks.mjs',
            'JS_ROOT' => resource_path('js'),
            'ESCPOS' => resource_path('js/services/escpos.js'),
        ])->input(json_encode([
            'transaction' => $transaction,
            'paperWidth' => $paperWidth,
        ]))->run(['node', $script]);
    } finally {
        File::deleteDirectory($dir);
    }

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true);
}

/**
 * Indeks baris pertama yang cocok, atau gagal dengan isi struknya.
 *
 * @param  list<string>  $lines
 */
function receiptLineIndex(array $lines, string $pattern): int
{
    foreach ($lines as $index => $line) {
        if (preg_match($pattern, $line) === 1) {
            return $index;
        }
    }

    throw new RuntimeException("Tidak ada baris yang cocok dengan {$pattern}:\n".implode("\n", $lines));
}

/**
 * Render `ReceiptModal.vue` yang sungguhan lewat server-renderer Vue, lalu
 * kembalikan teks struknya per baris.
 *
 * Struk layar tidak punya jalur test lain — ia komponen Vue, dan proyek ini
 * tidak memakai test runner JS — padahal ia yang dilihat kasir sebelum kertas
 * keluar. Yang dijalankan di sini berkas SFC aslinya, bukan salinan; tiga
 * tetangganya (Inertia, BrandMark, printer termal) diganti stub karena yang
 * diuji barisnya, bukan mereka.
 *
 * @param  array<string, mixed>  $transaction
 * @return list<string>
 */
function renderReceiptModalLines(array $transaction): array
{
    try {
        $hasNode = Process::run(['node', '--version'])->successful();
    } catch (Throwable) {
        $hasNode = false;
    }

    if (! $hasNode || ! File::isDirectory(base_path('node_modules/@vue/server-renderer'))) {
        test()->markTestSkipped('node atau @vue/server-renderer tidak tersedia, struk layar tidak bisa dirender.');
    }

    // Di dalam proyek, bukan di direktori sementara sistem: berkas hasil
    // kompilasi mengimpor `vue` dan `@/support/...`, dan keduanya hanya
    // teresolusi bila berkasnya berada di bawah akar proyek.
    $dir = storage_path('framework/testing/receipt-ssr-'.Str::random(8));
    File::ensureDirectoryExists($dir);

    File::put($dir.DIRECTORY_SEPARATOR.'stub-inertia.mjs', <<<'JS'
        export const usePage = () => ({ props: { auth: { tenant: { name: 'Toko Uji', logo_url: null } } } });
        JS);

    File::put($dir.DIRECTORY_SEPARATOR.'stub-empty-component.mjs', <<<'JS'
        export default { setup: () => () => null };
        JS);

    File::put($dir.DIRECTORY_SEPARATOR.'stub-printer.mjs', <<<'JS'
        import { ref } from 'vue';

        export function useThermalPrinter() {
            return { supports: { bluetooth: false, usb: false }, isConfigured: ref(false), printReceipt: async () => {} };
        }
        JS);

    File::put($dir.DIRECTORY_SEPARATOR.'hooks.mjs', <<<'JS'
        import { pathToFileURL } from 'node:url';
        import { extname, join } from 'node:path';

        const STUBS = {
            '@inertiajs/vue3': 'stub-inertia.mjs',
            '@/Components/BrandMark.vue': 'stub-empty-component.mjs',
            '@/Components/PrinterSetupModal.vue': 'stub-empty-component.mjs',
            '@/composables/useThermalPrinter': 'stub-printer.mjs',
        };

        export async function resolve(specifier, context, nextResolve) {
            if (STUBS[specifier]) {
                return nextResolve(pathToFileURL(join(process.env.HARNESS_DIR, STUBS[specifier])).href, context);
            }

            if (specifier.startsWith('@/')) {
                const target = join(process.env.JS_ROOT, specifier.slice(2));

                return nextResolve(pathToFileURL(extname(target) ? target : `${target}.js`).href, context);
            }

            return nextResolve(specifier, context);
        }
        JS);

    $script = $dir.DIRECTORY_SEPARATOR.'run.mjs';
    File::put($script, <<<'JS'
        import { register, createRequire } from 'node:module';
        import { readFileSync, writeFileSync } from 'node:fs';
        import { pathToFileURL } from 'node:url';
        import { join } from 'node:path';

        register(pathToFileURL(join(process.env.HARNESS_DIR, 'hooks.mjs')).href);

        const require = createRequire(join(process.env.HARNESS_DIR, 'noop.js'));
        const { parse, compileScript } = require('@vue/compiler-sfc');
        const { createSSRApp } = require('vue');
        const { renderToString } = require('@vue/server-renderer');

        const { descriptor, errors } = parse(readFileSync(process.env.SFC, 'utf8'), { filename: process.env.SFC });
        if (errors.length) throw new Error('parse: ' + JSON.stringify(errors));

        const compiled = compileScript(descriptor, { id: 'receipt-modal', inlineTemplate: true, templateOptions: { ssr: true } });
        const out = join(process.env.HARNESS_DIR, 'ReceiptModal.compiled.mjs');
        writeFileSync(out, compiled.content);

        const { default: ReceiptModal } = await import(pathToFileURL(out).href);
        const { transaction } = JSON.parse(readFileSync(0, 'utf8'));

        // Isi modal hidup di dalam <Teleport to="body">, jadi ia tidak ada di
        // markup yang dikembalikan renderToString — ia ada di ctx.teleports.
        const ctx = {};
        const html = await renderToString(createSSRApp(ReceiptModal, { show: true, transaction }), ctx);

        const lines = (Object.values(ctx.teleports ?? {}).join('\n') || html)
            .replace(/<[^>]+>/g, '\n')
            .split('\n')
            .map((line) => line.replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').trim())
            .filter(Boolean);

        process.stdout.write(JSON.stringify(lines));
        JS);

    try {
        $result = Process::env([
            'HARNESS_DIR' => $dir,
            'JS_ROOT' => resource_path('js'),
            'SFC' => resource_path('js/Components/ReceiptModal.vue'),
        ])->input(json_encode(['transaction' => $transaction]))->run(['node', $script]);
    } finally {
        File::deleteDirectory($dir);
    }

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true);
}

test('baris berdiskon tercetak dengan harga normal lalu potongannya', function (int $paperWidth, int $columns) {
    ['cashier' => $cashier, 'tea' => $tea, 'coffee' => $coffee, 'cash' => $cash] = receiptDiscountSale();

    DiscountRule::factory()->create([
        'tenant_id' => $tea->product->tenant_id,
        'product_variant_id' => $tea->id,
        'percent' => 30,
    ]);

    $transaction = receiptDiscountCheckout($cashier, $cash, [
        ['variant_id' => $tea->id, 'variant_name' => 'Teh Manis', 'qty' => 2, 'unit_price' => 7000],
        ['variant_id' => $coffee->id, 'variant_name' => 'Kopi', 'qty' => 1, 'unit_price' => 15000],
    ], 30000);

    $lines = renderThermalReceiptLines($transaction, $paperWidth);

    // Harga normal × qty, lalu potongannya TEPAT di bawahnya. Kolom kanan
    // dijumlahkan menurun: 20.000 − 6.000 = 14.000 yang dibayar.
    $teaLine = receiptLineIndex($lines, '/^  2 x Rp 10\.000 +Rp 20\.000$/');
    expect(strlen($lines[$teaLine]))->toBe($columns)
        ->and($lines[$teaLine + 1])->toMatch('/^  Diskon +-Rp 6\.000$/')
        ->and(strlen($lines[$teaLine + 1]))->toBe($columns);

    // Barang tanpa potongan tidak berubah sama sekali.
    $coffeeLine = receiptLineIndex($lines, '/^  1 x Rp 15\.000 +Rp 15\.000$/');
    expect($lines[$coffeeLine + 1])->not->toContain('Diskon');

    // Subtotal sudah bersih dari potongan; "Anda hemat" hanya keterangan.
    receiptLineIndex($lines, '/^Subtotal +Rp 29\.000$/');
    receiptLineIndex($lines, '/^TOTAL +Rp 29\.000$/');
    expect($lines[receiptLineIndex($lines, '/^Anda hemat +Rp 6\.000$/')])->toHaveLength($columns);
})->with([
    'kertas 58mm' => [58, 32],
    'kertas 80mm' => [80, 48],
]);

test('struk tanpa potongan tidak mencetak baris diskon maupun "Anda hemat"', function () {
    ['cashier' => $cashier, 'coffee' => $coffee, 'cash' => $cash] = receiptDiscountSale();

    $transaction = receiptDiscountCheckout($cashier, $cash, [
        ['variant_id' => $coffee->id, 'variant_name' => 'Kopi', 'qty' => 2, 'unit_price' => 15000],
    ], 30000);

    $lines = renderThermalReceiptLines($transaction);

    receiptLineIndex($lines, '/^  2 x Rp 15\.000 +Rp 30\.000$/');
    expect(implode("\n", $lines))->not->toContain('Diskon')
        ->not->toContain('Anda hemat');
});

test('harga khusus owner tercetak sebagai Diskon, tanpa alasan yang ditulis untuk owner', function () {
    ['owner' => $owner, 'coffee' => $coffee, 'cash' => $cash] = receiptDiscountSale();

    $transaction = receiptDiscountCheckout($owner, $cash, [[
        'variant_id' => $coffee->id,
        'variant_name' => 'Kopi',
        'qty' => 1,
        'unit_price' => 15000,
        'override_unit_price' => 9000,
        'discount_reason' => 'Teman lama owner',
    ]], 9000);

    $lines = renderThermalReceiptLines($transaction);

    $coffeeLine = receiptLineIndex($lines, '/^  1 x Rp 15\.000 +Rp 15\.000$/');
    expect($lines[$coffeeLine + 1])->toMatch('/^  Diskon +-Rp 6\.000$/')
        ->and(implode("\n", $lines))->not->toContain('Teman lama owner');
});

test('baris tanpa jejak potongan tidak dikarangkan potongannya', function () {
    ['cashier' => $cashier, 'tea' => $tea, 'cash' => $cash] = receiptDiscountSale();

    $transaction = receiptDiscountCheckout($cashier, $cash, [
        ['variant_id' => $tea->id, 'variant_name' => 'Teh Manis', 'qty' => 2, 'unit_price' => 10000],
    ], 20000);

    // Bentuk baris penjualan offline hari ini ([BL-115]): dibayar lebih murah
    // dari katalog, tapi tanpa `original_unit_price` dan `discount_amount`.
    // Struk tidak boleh menebak potongan yang tidak pernah tercatat.
    $transaction['items'][0] = array_merge($transaction['items'][0], [
        'unit_price' => '7000.00',
        'subtotal' => '14000.00',
        'original_unit_price' => null,
        'discount_amount' => '0.00',
    ]);

    $lines = renderThermalReceiptLines($transaction);

    receiptLineIndex($lines, '/^  2 x Rp 7\.000 +Rp 14\.000$/');
    expect(implode("\n", $lines))->not->toContain('Diskon')
        ->not->toContain('Anda hemat');
});

test('struk layar menampilkan potongan yang sama dengan struk termal', function () {
    ['cashier' => $cashier, 'tea' => $tea, 'coffee' => $coffee, 'cash' => $cash] = receiptDiscountSale();

    DiscountRule::factory()->create([
        'tenant_id' => $tea->product->tenant_id,
        'product_variant_id' => $tea->id,
        'percent' => 30,
    ]);

    $transaction = receiptDiscountCheckout($cashier, $cash, [
        ['variant_id' => $tea->id, 'variant_name' => 'Teh Manis', 'qty' => 2, 'unit_price' => 7000],
        ['variant_id' => $coffee->id, 'variant_name' => 'Kopi', 'qty' => 1, 'unit_price' => 15000],
    ], 30000);

    $lines = renderReceiptModalLines($transaction);

    // Urutannya sama dengan kertas: nama, jumlah sebelum dipotong, qty × harga
    // normal, lalu potongannya. Tanda kalinya "×" di layar, "x" di printer —
    // kertas termal tidak punya karakter itu.
    $tea = receiptLineIndex($lines, '/^Teh Manis$/');
    expect(array_slice($lines, $tea, 5))->toBe([
        'Teh Manis',
        'Rp 20.000',
        '2 × Rp 10.000',
        'Diskon',
        '-Rp 6.000',
    ]);

    $coffee = receiptLineIndex($lines, '/^Kopi$/');
    expect(array_slice($lines, $coffee, 3))->toBe(['Kopi', 'Rp 15.000', '1 × Rp 15.000']);

    $savings = receiptLineIndex($lines, '/^Anda hemat$/');
    expect($lines[$savings + 1])->toBe('Rp 6.000');

    // Subtotal tetap angka beku dari server, sudah bersih dari potongan.
    expect($lines[receiptLineIndex($lines, '/^Subtotal$/') + 1])->toBe('Rp 29.000')
        ->and($lines[receiptLineIndex($lines, '/^TOTAL$/') + 1])->toBe('Rp 29.000');
});

test('struk layar tanpa potongan tidak menampilkan baris diskon', function () {
    ['cashier' => $cashier, 'coffee' => $coffee, 'cash' => $cash] = receiptDiscountSale();

    $transaction = receiptDiscountCheckout($cashier, $cash, [
        ['variant_id' => $coffee->id, 'variant_name' => 'Kopi', 'qty' => 2, 'unit_price' => 15000],
    ], 30000);

    $lines = renderReceiptModalLines($transaction);

    $coffee = receiptLineIndex($lines, '/^Kopi$/');
    expect(array_slice($lines, $coffee, 3))->toBe(['Kopi', 'Rp 30.000', '2 × Rp 15.000'])
        ->and(implode("\n", $lines))->not->toContain('Diskon')
        ->not->toContain('Anda hemat');
});
