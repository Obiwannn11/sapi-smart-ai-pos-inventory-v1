<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UpsellEvent;
use App\Services\DiscountService;
use App\Services\PaymentProofService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $tenantId = $user->tenant_id;

        return [
            // Items
            'items' => 'required|array|min:1',
            'items.*.variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')->where(function ($q) use ($tenantId) {
                    $q->whereIn('product_id', Product::where('tenant_id', $tenantId)->pluck('id'));
                }),
            ],
            'items.*.variant_name' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.id' => [
                'required',
                Rule::exists('modifiers', 'id')->where(function ($q) use ($tenantId) {
                    $q->whereIn('modifier_group_id',
                        \App\Models\ModifierGroup::where('tenant_id', $tenantId)->pluck('id')
                    );
                }),
            ],
            'items.*.modifiers.*.name' => 'required|string|max:255',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',

            // Harga khusus di bawah lantai margin ([BL-018]). Hanya owner, dan
            // itu ditegakkan di TransactionService — bukan di sini, karena
            // kegagalan wewenang harus berbunyi sebagai penolakan yang jelas,
            // bukan sebagai galat validasi bentuk.
            'items.*.override_unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_reason' => 'nullable|string|max:200',

            // Payments (nullable for open bill)
            'payments' => 'nullable|array|min:1',
            'payments.*.payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference_code' => 'nullable|string|max:255',

            // Token foto bukti bayar ([BL-075]) — bukan berkasnya.
            //
            // Fotonya sudah diunggah lebih dulu lewat endpoint tersendiri, dan
            // yang menyeberang di sini hanya sebuah UUID. Dengan begitu payload
            // checkout tetap JSON: menukarnya jadi multipart demi satu foto
            // akan mengubah setiap angka jadi string di seluruh validasi yang
            // menjaga uang. Alasan lengkapnya di PaymentProofService.
            'payments.*.proof_token' => 'nullable|uuid',

            // Notes
            'notes' => 'nullable|string|max:1000',

            // Identitas pesanan — nama pelanggan / nomor meja.
            //
            // Keduanya `nullable` dan tidak pernah saling mewajibkan. Mode
            // identitas milik owner mengatur apa yang DITANYAKAN kasir, bukan
            // apa yang diterima server: kasir yang melewati modal identitas di
            // tengah antrean panjang tetap harus bisa menyelesaikan penjualan.
            // Prinsip yang sama sudah dipakai untuk `upsell_events` ([BL-026]).
            //
            // `max:10` mengikuti lebar kolomnya, bukan angka yang dikarang —
            // batas yang lebih longgar dari kolomnya hanya memindahkan
            // kegagalan dari validasi ke driver database.
            'customer_name' => 'nullable|string|max:100',
            'table_number' => 'nullable|string|max:10',

            // Open bill flag
            'is_open_bill' => 'nullable|boolean',

            // Idempotency key (client-generated per checkout)
            'client_uuid' => 'nullable|uuid',

            // Nasib saran upsell yang muncul pada keranjang ini.
            //
            // Sengaja TIDAK memakai Rule::exists: kepemilikan tenant diperiksa
            // di UpsellEventRecorder, yang menolkan FK asing alih-alih menolak
            // request. Statistik upsell tidak boleh punya kuasa menggagalkan
            // penjualan yang sah.
            'upsell_events' => 'nullable|array|max:20',
            'upsell_events.*.type' => 'required|string|in:'.implode(',', UpsellEvent::types()),
            'upsell_events.*.status' => 'required|string|in:'.implode(',', UpsellEvent::statuses()),
            'upsell_events.*.reason' => 'nullable|string|max:50',
            'upsell_events.*.label' => 'required|string|max:255',
            'upsell_events.*.extra_amount' => 'nullable|numeric|min:0',
            'upsell_events.*.trigger_variant_id' => 'nullable|integer',
            'upsell_events.*.suggested_variant_id' => 'nullable|integer',
            'upsell_events.*.suggested_modifier_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal 1 item harus ada di transaksi.',
            'payments.required' => 'Metode pembayaran harus dipilih.',
        ];
    }

    /**
     * Validasi tambahan: total bayar >= total belanja.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Skip payment validation for open bill
            if ($this->boolean('is_open_bill')) {
                return;
            }

            // Payments required for non-open-bill
            if (empty($this->input('payments'))) {
                $validator->errors()->add('payments', 'Metode pembayaran harus dipilih.');

                return;
            }

            $totalBayar = collect($this->input('payments', []))->sum('amount');

            if ($totalBayar < $this->expectedTotal()) {
                $validator->errors()->add('payments', 'Total pembayaran kurang dari total belanja.');
            }

            $this->validatePaymentProofs($validator, $this->input('payments', []));
        });
    }

    /**
     * Foto bukti bayar wajib untuk setiap pembayaran non-tunai, bila tokonya
     * menyalakannya ([BL-075]).
     *
     * **Ditegakkan di server, bukan hanya dengan menyembunyikan tombolnya.**
     * Modal pembayaran memang menahan tombol Bayar, tapi aturan yang hanya
     * hidup di layar adalah aturan yang tidak berlaku bagi siapa pun yang
     * mengirim request sendiri.
     *
     * Aturannya sendiri di PaymentProofService::missingProofs(), dipakai
     * bersama jalur bayar open bill.
     *
     * @param  array<int, array<string, mixed>>  $payments
     */
    private function validatePaymentProofs($validator, array $payments): void
    {
        $missing = app(PaymentProofService::class)
            ->missingProofs($payments, Auth::user()?->tenant);

        foreach ($missing as $index => $message) {
            $validator->errors()->add("payments.{$index}.proof_token", $message);
        }
    }

    /**
     * Total belanja menurut SERVER, bukan menurut angka kiriman klien.
     *
     * Dulu dijumlahkan dari `items.*.unit_price` apa adanya, dan itu salah
     * dalam DUA arah:
     *
     *   Ia tidak menjaga apa pun. Klien yang mengirim `unit_price` kecil ikut
     *   menurunkan ambang yang harus ia bayar — penjaga yang bisa dilunakkan
     *   oleh pihak yang sedang dijaga.
     *
     *   Dan sejak `[BL-018]`, ia menolak yang sah: server memotong harganya,
     *   klien mengirim harga katalog, dan penjualan berdiskon yang dibayar PAS
     *   terbaca sebagai kurang bayar.
     *
     * Harga khusus owner ikut diperhitungkan supaya ambangnya cocok dengan yang
     * benar-benar akan ditagih; WEWENANGNYA sendiri diperiksa
     * TransactionService, yang menolak dengan pesan yang bisa dibaca kasir.
     */
    private function expectedTotal(): float
    {
        $items = $this->input('items', []);

        $variants = ProductVariant::whereIn('id', collect($items)->pluck('variant_id')->filter())
            ->get()
            ->keyBy('id');

        $tenant = Auth::user()?->tenant;
        $discounts = app(DiscountService::class);

        $total = 0.0;

        foreach ($items as $item) {
            $variant = $variants->get($item['variant_id'] ?? null);
            $qty = (int) ($item['qty'] ?? 0);

            if (! $variant || $qty < 1) {
                continue;
            }

            $unitPrice = $tenant
                ? $discounts->priceFor($variant, $tenant)['price']
                : (float) $variant->price;

            if (($item['override_unit_price'] ?? null) !== null) {
                $unitPrice = $discounts->roundUp((float) $item['override_unit_price']);
            }

            $itemTotal = $unitPrice * $qty;

            if (! empty($item['modifiers'])) {
                $itemTotal += collect($item['modifiers'])->sum('extra_price') * $qty;
            }

            $total += $itemTotal;
        }

        return $total;
    }
}
