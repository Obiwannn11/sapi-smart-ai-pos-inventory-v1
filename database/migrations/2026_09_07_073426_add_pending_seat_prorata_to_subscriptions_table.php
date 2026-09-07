<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catat pembelian seat yang belum tertutup tagihan penuh mana pun (`[BL-070]`).
 *
 * Sampai sekarang `purchased_extra_seats` hanya cacahan: ia menjawab "berapa
 * seat", tidak pernah "sejak kapan". Selama seat yang dibeli di tengah periode
 * gratis sampai periode habis, itu memang cukup. Begitu hari-harinya ditagih,
 * tanggal belinya jadi angka yang menentukan uang — dan satu-satunya jejaknya
 * hari ini ada di `platform_audit_logs`, tempat yang tidak boleh jadi sumber
 * kebenaran penagihan.
 *
 * **Penyebutnya ikut dibekukan di dalam entri, bukan dihitung ulang nanti.**
 * Tiap entri menyimpan `period_days` — panjang periode tempat pembelian itu
 * jatuh. Menghitungnya ulang saat tagihan disusun berarti mengukur periode yang
 * sudah lewat dengan jangkar hari ini, dan itu meleset tiap kali `Februari` ada
 * di antaranya. Alasannya sama persis dengan `pricing_context.billing_breakdown`
 * (`[BL-053]`): yang menentukan uang dibekukan saat kejadiannya, tidak pernah
 * direkonstruksi.
 *
 * Bentuk tiap entri:
 *
 * ```
 * {"on": "2026-09-10", "seats": 2, "period_days": 30}
 * ```
 *
 * Dikosongkan begitu tagihan yang memuatnya benar-benar tersimpan — bukan saat
 * tagihannya disusun. Penyusun tagihan tidak menyimpan apa pun, dan entri yang
 * hangus untuk tagihan yang batal terbit adalah hari-hari yang tidak pernah
 * tertagih ke siapa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('pending_seat_prorata')->nullable()->after('seat_release_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('pending_seat_prorata');
        });
    }
};
