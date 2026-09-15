<?php

use App\Services\Upsell\Suggestion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Saran jual tidak boleh beranak-pinak, dan batasnya adalah batas TAWARAN.
 *
 * Ditemukan pemilik saat mencoba sebagai kasir: Espresso Single menawarkan
 * Croissant, Croissant yang diterima menawarkan Cookie, Cookie menawarkan Jus —
 * dan Double hasil naik ukuran menawarkan Triple. Dengan `max_per_transaction`
 * 3, satu pelanggan bisa menerima enam tawaran, karena batasnya hanya memotong
 * saran yang sedang MENUNGGU; yang sudah dijawab keluar dari daftar dan
 * slotnya langsung terisi lagi.
 *
 * Pemilihannya terjadi di `resources/js/composables/useUpsell.js`, bukan di
 * server, jadi test ini menjalankan composable itu sungguhan lewat node.
 * Menggrep sumbernya hanya membuktikan sebuah nama fungsi masih ada.
 */
const SINGLE = 1;
const DOUBLE = 2;
const TRIPLE = 3;
const CROISSANT = 10;
const COOKIE = 11;
const BAGEL = 20;
const JUICE = 21;
const CAKE = 22;

/**
 * @return array<string, mixed>
 */
function chainSuggestion(string $type, int $trigger, int $target, int $score): array
{
    return [
        'key' => Suggestion::keyFor($type, $trigger, $target),
        'type' => $type,
        'score' => $score,
        'label' => "Varian {$target}",
        'trigger_variant_id' => $trigger,
        'suggested_variant_id' => $target,
        'suggested_modifier_id' => null,
        'extra_amount' => 0,
    ];
}

/**
 * Jalankan satu skenario keranjang di composable kasir.
 *
 * @param  array<int, list<array<string, mixed>>>  $byVariant
 * @param  list<int>  $cart
 * @param  list<array{accept?: string, reject?: string, retract?: string}>  $steps
 * @return list<list<string>> kunci saran yang menunggu: sebelum langkah pertama, lalu sesudah tiap langkah
 */
function runUpsellScenario(array $byVariant, array $cart, array $steps, int $max = 3): array
{
    try {
        $hasNode = Process::run(['node', '--version'])->successful();
    } catch (Throwable) {
        $hasNode = false;
    }

    if (! $hasNode) {
        test()->markTestSkipped('node tidak tersedia, composable kasir tidak bisa dijalankan.');
    }

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'upsell-chain-'.Str::random(8);
    File::ensureDirectoryExists($dir);

    $script = $dir.DIRECTORY_SEPARATOR.'run.mjs';
    File::put($script, <<<'JS'
        import { readFileSync, writeFileSync } from 'node:fs';
        import { pathToFileURL } from 'node:url';

        const vueUrl = pathToFileURL(process.env.VUE_ENTRY).href;
        const copy = process.env.COMPOSABLE_COPY;
        writeFileSync(copy, readFileSync(process.env.COMPOSABLE, 'utf8').replace("from 'vue'", `from '${vueUrl}'`));

        const { ref, nextTick } = await import(vueUrl);
        const { useUpsell } = await import(pathToFileURL(copy).href);

        const scenario = JSON.parse(readFileSync(0, 'utf8'));
        const cart = ref(scenario.cart.map((variant_id) => ({ variant_id, qty: 1, modifiers: [] })));

        const upsell = useUpsell(ref(scenario.index), cart, {
            getVariantStock: () => 99,
            getCartQtyForVariant: (id) => cart.value
                .filter((line) => line.variant_id === id)
                .reduce((sum, line) => sum + line.qty, 0),
            isApplied: (entry) => cart.value.some((line) => line.variant_id === entry.suggested_variant_id),
        });

        const pending = () => upsell.suggestions.value.map((suggestion) => suggestion.key);
        const snapshots = [pending()];

        // Menyentuh keranjang seperti `applyUpsell`/`undoUpsell` di POS.vue.
        for (const step of scenario.steps) {
            const [action, key] = Object.entries(step)[0];
            const source = action === 'retract' ? upsell.accepted.value : upsell.suggestions.value;
            const suggestion = source.find((item) => item.key === key);

            if (!suggestion) throw new Error(`${action} ${key}: saran itu tidak sedang tersedia`);

            if (action === 'accept') {
                if (suggestion.type === 'upsize') {
                    cart.value.find((line) => line.variant_id === suggestion.trigger_variant_id).variant_id = suggestion.suggested_variant_id;
                    upsell.accept(suggestion, 0, { variant_id: suggestion.trigger_variant_id });
                } else {
                    cart.value.push({ variant_id: suggestion.suggested_variant_id, qty: 1, modifiers: [] });
                    upsell.accept(suggestion, 0);
                }
            } else if (action === 'reject') {
                upsell.reject(suggestion);
            } else {
                const index = cart.value.findIndex((line) => line.variant_id === suggestion.suggested_variant_id);

                if (suggestion.type === 'upsize') {
                    cart.value[index].variant_id = suggestion.restore.variant_id;
                } else {
                    cart.value.splice(index, 1);
                }

                upsell.retract(suggestion);
            }

            await nextTick();
            snapshots.push(pending());
        }

        process.stdout.write(JSON.stringify(snapshots));
        JS);

    try {
        $result = Process::env([
            'VUE_ENTRY' => base_path('node_modules/vue/index.mjs'),
            'COMPOSABLE' => resource_path('js/composables/useUpsell.js'),
            'COMPOSABLE_COPY' => $dir.DIRECTORY_SEPARATOR.'useUpsell.mjs',
        ])->input(json_encode([
            'index' => [
                'max_per_transaction' => $max,
                'mandatory' => false,
                'cart_level' => [],
                'by_variant' => (object) $byVariant,
            ],
            'cart' => $cart,
            'steps' => $steps,
        ]))->run(['node', $script]);
    } finally {
        File::deleteDirectory($dir);
    }

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true);
}

test('barang yang masuk dari saran tidak memicu saran baru', function () {
    $snapshots = runUpsellScenario([
        SINGLE => [
            chainSuggestion('manual', SINGLE, CROISSANT, 60),
            chainSuggestion('upsize', SINGLE, DOUBLE, 40),
        ],
        CROISSANT => [chainSuggestion('manual', CROISSANT, COOKIE, 60)],
    ], [SINGLE], [
        ['accept' => Suggestion::keyFor('manual', SINGLE, CROISSANT)],
    ]);

    // Croissant diterima dari Espresso. Yang tersisa hanya tawaran lain untuk
    // Espresso itu sendiri — bukan Cookie "karena ada Croissant".
    expect($snapshots[1])->toBe([Suggestion::keyFor('upsize', SINGLE, DOUBLE)]);
});

test('naik ukuran tidak memicu naik ukuran lagi dan tidak memutus tawaran lain untuk barang yang sama', function () {
    $snapshots = runUpsellScenario([
        SINGLE => [
            chainSuggestion('manual', SINGLE, CROISSANT, 60),
            chainSuggestion('upsize', SINGLE, DOUBLE, 40),
        ],
        DOUBLE => [chainSuggestion('upsize', DOUBLE, TRIPLE, 40)],
    ], [SINGLE], [
        ['accept' => Suggestion::keyFor('upsize', SINGLE, DOUBLE)],
    ]);

    // Double bukan pilihan pelanggan, jadi tidak menawarkan Triple. Tapi baris
    // itu tetap kopi pesanan pelanggan, jadi Croissant-nya tetap ditawarkan.
    expect($snapshots[1])->toBe([Suggestion::keyFor('manual', SINGLE, CROISSANT)]);
});

test('batas per transaksi menghitung tawaran yang sudah dijawab, bukan hanya yang menunggu', function () {
    $snapshots = runUpsellScenario([
        SINGLE => [
            chainSuggestion('manual', SINGLE, CROISSANT, 60),
            chainSuggestion('upsize', SINGLE, DOUBLE, 40),
        ],
        BAGEL => [
            chainSuggestion('manual', BAGEL, JUICE, 50),
            chainSuggestion('manual', BAGEL, CAKE, 30),
        ],
    ], [SINGLE, BAGEL], [
        ['reject' => Suggestion::keyFor('manual', SINGLE, CROISSANT)],
        ['accept' => Suggestion::keyFor('manual', BAGEL, JUICE)],
        ['reject' => Suggestion::keyFor('upsize', SINGLE, DOUBLE)],
    ]);

    expect($snapshots)->toBe([
        [
            Suggestion::keyFor('manual', SINGLE, CROISSANT),
            Suggestion::keyFor('manual', BAGEL, JUICE),
            Suggestion::keyFor('upsize', SINGLE, DOUBLE),
        ],
        // Yang ditolak tetap memakan jatah: Cake, kandidat keempat, tidak naik
        // menggantikannya.
        [
            Suggestion::keyFor('manual', BAGEL, JUICE),
            Suggestion::keyFor('upsize', SINGLE, DOUBLE),
        ],
        [Suggestion::keyFor('upsize', SINGLE, DOUBLE)],
        [],
    ]);
});

test('saran yang ditarik kembali mengembalikan jatahnya', function () {
    $croissant = Suggestion::keyFor('manual', SINGLE, CROISSANT);

    $snapshots = runUpsellScenario([
        SINGLE => [
            chainSuggestion('manual', SINGLE, CROISSANT, 60),
            chainSuggestion('upsize', SINGLE, DOUBLE, 40),
        ],
    ], [SINGLE], [
        ['accept' => $croissant],
        ['retract' => $croissant],
    ], max: 1);

    // Salah pencet bukan tawaran yang terjadi ([BL-092]); jatahnya kembali,
    // dan saran yang sama menunggu keputusan lagi.
    expect($snapshots)->toBe([[$croissant], [], [$croissant]]);
});
