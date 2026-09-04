<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUpsellRuleRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Models\UpsellRule;
use App\Services\Upsell\UpsellIndexBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aturan saran jual yang ditulis owner ([BL-074]).
 *
 * **Halamannya berdiri sendiri, bukan ditambahkan ke Pengaturan.** Editornya
 * butuh tabel, pencarian produk, dan jendela tanggal; menjejalkannya ke formulir
 * Pengaturan akan mengulang persis keluhan `[BL-039]` yang baru saja dibereskan
 * dengan memecah "Profil Usaha" jadi tiga halaman.
 *
 * **Owner-eksklusif, bukan modul RBAC baru.** Memilih barang mana yang didorong
 * adalah keputusan pemilik usaha, bukan tugas yang dilimpahkan — dan
 * menggantungkannya pada permission `reports` akan memberi kuasa MENULIS kepada
 * siapa pun yang hanya diberi hak MEMBACA laporan.
 */
class UpsellRuleController extends Controller
{
    public function __construct(private UpsellIndexBuilder $upsellIndexBuilder) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Owner/UpsellRules/Index', [
            // Ditunda ([BL-037]): tombol tambah dan formulirnya sudah bisa
            // dipakai sejak cat pertama, daftarnya menyusul.
            'rules' => Inertia::defer(fn () => UpsellRule::with([
                'triggerVariant:id,product_id,name',
                'triggerVariant.product:id,name',
                'suggestedVariant:id,product_id,name,price,stock',
                'suggestedVariant.product:id,name',
            ])
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->get()),

            // Daftar varian untuk kedua pemilihnya. Dikirim sekali dan dipakai
            // dua kali — pemicu dan yang disarankan — supaya tidak ada endpoint
            // pencarian tersendiri untuk katalog yang seukuran ini.
            'variants' => Inertia::defer(fn () => Product::where('is_active', true)
                ->with('variants:id,product_id,name,price,stock')
                ->orderBy('name')
                ->get()
                ->flatMap(fn (Product $product) => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'label' => $product->name.' - '.$variant->name,
                    'price' => (float) $variant->price,
                    'stock' => $variant->stock,
                ]))
                ->values()),

            // Kelompok tersendiri, bukan menumpang daftar aturan ([BL-092]):
            // merakit indeks menelusuri seluruh katalog, stok, dan riwayat
            // penjualan — menyatukannya dengan tabel aturan berarti tabelnya
            // ikut menunggu pekerjaan yang tidak ada hubungannya dengannya.
            'preview' => Inertia::defer(fn () => $this->slotPreview($request->user()->tenant), 'pratinjau'),
        ]);
    }

    /**
     * Apa yang BENAR-BENAR muncul di kasir hari ini, beserta yang tergeser.
     *
     * Halaman ini sebelumnya hanya memperlihatkan separuh kenyataan: aturan
     * yang owner tulis sendiri, tanpa satu pun saran yang ditemukan mesin dari
     * stok. Owner jadi tidak punya cara melihat siapa yang sedang mengisi tiga
     * slot kasir — dan aturan yang tidak muncul terbaca sebagai fitur rusak,
     * padahal ia hanya kalah skor atau stoknya habis ([BL-092]).
     *
     * Pemilihan slotnya memakai `UpsellIndexBuilder`, kode yang sama persis
     * dengan yang dipakai kasir; yang berbeda hanya keranjang yang diandaikan.
     *
     * @return array{enabled: bool, max_per_transaction: int, disabled_types: list<string>, cart_level: list<array<string, mixed>>, triggers: list<array<string, mixed>>, triggers_truncated: int}
     */
    private function slotPreview(Tenant $tenant): array
    {
        $index = $this->upsellIndexBuilder->build($tenant);
        $max = (int) $index['max_per_transaction'];

        // Keranjang tanpa satu pun barang pemicu: hanya kandidat tanpa-pemicu
        // yang berebut. Diurutkan di sini, bukan lewat `rankForCart()`, karena
        // fungsi itu sengaja mengembalikan kosong untuk keranjang kosong —
        // keranjang kosong memang tidak boleh memicu saran apa pun.
        $cartLevel = $index['cart_level'];
        usort($cartLevel, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $triggerIds = array_map('intval', array_keys($index['by_variant']));

        $labels = $this->variantLabels($triggerIds);

        $triggers = [];

        foreach ($triggerIds as $triggerId) {
            $triggers[] = [
                'variant_id' => $triggerId,
                'label' => $labels[$triggerId] ?? "Varian #{$triggerId}",
                'slots' => $this->asSlots($this->upsellIndexBuilder->rankForCart($index, [$triggerId]), $max),
            ];
        }

        usort($triggers, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        // Katalog besar bisa punya ratusan pemicu. Daftar sepanjang itu tidak
        // dibaca siapa pun; jumlah sisanya tetap disebut supaya owner tahu
        // yang dilihatnya belum seluruhnya.
        $limit = 25;

        return [
            'enabled' => (bool) config('upsell.enabled', true),
            'max_per_transaction' => $max,
            'disabled_types' => $this->disabledTypes(),
            'cart_level' => $this->asSlots($cartLevel, $max),
            'triggers' => array_slice($triggers, 0, $limit),
            'triggers_truncated' => max(0, count($triggers) - $limit),
        ];
    }

    /**
     * Tandai mana yang dapat slot dan mana yang tergeser batas tampilan.
     *
     * @param  list<array<string, mixed>>  $ranked
     * @return list<array<string, mixed>>
     */
    private function asSlots(array $ranked, int $max): array
    {
        return array_values(array_map(fn (array $suggestion, int $position) => [
            'key' => $suggestion['key'],
            'type' => $suggestion['type'],
            'reason' => $suggestion['reason'],
            'label' => $suggestion['label'],
            'note' => $suggestion['note'],
            'extra_amount' => $suggestion['extra_amount'],
            'is_manual' => $suggestion['type'] === UpsellEvent::TYPE_MANUAL,
            'wins_slot' => $position < $max,
        ], $ranked, array_keys($ranked)));
    }

    /**
     * Nama varian pemicu, sekali query untuk seluruh daftar.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function variantLabels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ProductVariant::whereIn('id', $ids)
            ->with('product:id,name')
            ->get(['id', 'product_id', 'name'])
            ->mapWithKeys(fn (ProductVariant $variant) => [
                $variant->id => $variant->product !== null
                    ? $variant->product->name.' - '.$variant->name
                    : $variant->name,
            ])
            ->all();
    }

    /**
     * Jenis saran yang dimatikan lewat `config/upsell.php` — saklar darurat
     * yang, kalau tidak disebutkan di layar, membuat owner mengira aturannya
     * sendiri yang rusak.
     *
     * @return list<string>
     */
    private function disabledTypes(): array
    {
        return array_values(array_keys(array_filter(
            (array) config('upsell.types', []),
            fn ($enabled) => ! $enabled,
        )));
    }

    public function store(StoreUpsellRuleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Aturan baru mendarat DI ATAS daftar, bukan di dasarnya. Slot kasir
        // hanya tiga; aturan yang lahir di urutan terakhir tidak muncul di mana
        // pun, dan owner yang baru saja menuliskannya menyimpulkan fiturnya
        // rusak. Nilai yang dikirim eksplisit tetap dihormati.
        $data['priority'] ??= (int) UpsellRule::max('priority') + 1;

        UpsellRule::create($data);

        return back()->with('success', 'Aturan saran jual ditambahkan.');
    }

    public function update(StoreUpsellRuleRequest $request, UpsellRule $upsellRule): RedirectResponse
    {
        $upsellRule->update($request->validated());

        return back()->with('success', 'Aturan saran jual diperbarui.');
    }

    /**
     * Nyalakan/matikan satu aturan.
     *
     * Terpisah dari `update()` dengan sengaja: mematikan aturan adalah tindakan
     * satu klik dari tabel, dan memaksanya melewati validasi formulir penuh
     * berarti aturan yang produknya sudah terhapus tidak bisa dimatikan sama
     * sekali — persis saat owner paling ingin mematikannya.
     */
    public function toggle(UpsellRule $upsellRule): RedirectResponse
    {
        $upsellRule->update(['is_active' => ! $upsellRule->is_active]);

        return back()->with('success', $upsellRule->is_active
            ? 'Aturan dinyalakan.'
            : 'Aturan dimatikan — kasir tidak lagi melihatnya.');
    }

    /**
     * Geser satu aturan satu langkah ke atas atau ke bawah.
     *
     * Menggantikan kolom isian "Urutan" berisi angka 0–999. Angka prioritas
     * adalah cara MESIN mengurutkan; owner yang ingin sebuah aturan tampil
     * lebih dulu tidak sedang memikirkan bilangan, ia sedang menunjuk baris.
     *
     * Seluruh prioritas DITULIS ULANG, bukan ditukar dua-dua: nilai bawaannya
     * 0, jadi aturan yang belum pernah disentuh semuanya seri dan urutannya
     * jatuh ke `id`. Menukar dua angka nol tidak memindahkan apa pun di layar.
     */
    public function move(Request $request, UpsellRule $upsellRule): RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        // Urutan yang sama persis dengan yang dipakai `index()` — kalau kedua
        // urutan ini berbeda, panahnya akan memindahkan baris yang tidak
        // ditunjuk owner.
        $rules = UpsellRule::orderByDesc('priority')->orderByDesc('id')->get()->values();

        $from = $rules->search(fn (UpsellRule $rule) => $rule->is($upsellRule));
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if ($from === false || $to < 0 || $to >= $rules->count()) {
            return back();
        }

        $ordered = $rules->all();
        [$ordered[$from], $ordered[$to]] = [$ordered[$to], $ordered[$from]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $rule) {
                $rule->update(['priority' => count($ordered) - $position]);
            }
        });

        return back();
    }

    public function destroy(UpsellRule $upsellRule): RedirectResponse
    {
        $upsellRule->delete();

        return back()->with('success', 'Aturan saran jual dihapus.');
    }
}
