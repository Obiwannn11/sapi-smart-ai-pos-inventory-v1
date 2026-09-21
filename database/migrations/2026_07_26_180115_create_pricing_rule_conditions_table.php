<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aturan harga berpindah dari KOLOM TETAP ke BARIS BERKRITERIA.
     *
     * Sebelumnya dimensi harga tertanam di bentuk tabel: `min_revenue` dan
     * `max_revenue` berarti omzet adalah satu-satunya sumbu yang boleh
     * menentukan tarif. Menambah kategori ketiga menuntut migration, perubahan
     * `PricingService`, perubahan form panel, dan deploy — persis beban yang
     * ingin dihindari pemilik SaaS.
     *
     * Sekarang satu aturan punya banyak syarat (`dimension`, `operator`,
     * `value`). Kategori baru = baris baru dari panel, bukan rilis baru.
     *
     * `value` disimpan sebagai string, bukan decimal, karena dimensi bertipe
     * atribut (tipe usaha, wilayah) tidak berupa angka. Penafsirannya milik
     * katalog di `config/pricing-dimensions.php`, yang tahu tipe tiap dimensi.
     *
     * `operator` disimpan sebagai string, BUKAN enum: SQLite (dipakai test)
     * memperlakukan enum sebagai varchar berpembatas check, dan menambah
     * operator baru kelak akan menuntut membangun ulang tabel.
     */
    public function up(): void
    {
        Schema::create('pricing_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_rule_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 50);
            $table->string('operator', 10);
            $table->string('value');
            $table->timestamps();
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            // Yang menang adalah aturan berprioritas tertinggi yang SELURUH
            // syaratnya terpenuhi. Tanpa penanda ini, dua aturan yang sama-sama
            // cocok — misalnya "omzet kecil" dan "omzet kecil DAN kuliner" —
            // tidak punya cara sah untuk saling mendahului.
            $table->unsignedInteger('priority')->default(0)->after('label');
        });

        $now = now();

        // Bracket lama DIPINDAHKAN, bukan disemai ulang dari config. Menyemai
        // ulang akan membuang tarif yang sudah disunting pemilik SaaS lewat
        // panel sejak Tahap D.
        foreach (DB::table('pricing_rules')->get() as $rule) {
            $conditions = [[
                'pricing_rule_id' => $rule->id,
                'dimension' => 'monthly_revenue',
                'operator' => 'gte',
                'value' => (string) $rule->min_revenue,
                'created_at' => $now,
                'updated_at' => $now,
            ]];

            // Batas atas EKSKLUSIF, persis seperti `PricingRule::covers()` dulu
            // memperlakukannya. Membuatnya inklusif di sini akan membuat omzet
            // yang tepat di batas cocok pada dua bracket sekaligus.
            if ($rule->max_revenue !== null) {
                $conditions[] = [
                    'pricing_rule_id' => $rule->id,
                    'dimension' => 'monthly_revenue',
                    'operator' => 'lt',
                    'value' => (string) $rule->max_revenue,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('pricing_rule_conditions')->insert($conditions);
        }

        // Indeks lama menyebut kolom yang sebentar lagi hilang, jadi ia harus
        // pergi lebih dulu — pada SQLite, membuang kolom berarti membangun ulang
        // tabelnya berikut seluruh indeks yang menempel.
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropIndex(['effective_from', 'min_revenue']);
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['min_revenue', 'max_revenue']);
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->index(['effective_from', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropIndex(['effective_from', 'priority']);
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->decimal('min_revenue', 14, 2)->default(0);
            $table->decimal('max_revenue', 14, 2)->nullable();
        });

        // Hanya syarat beromzet yang bisa pulang ke bentuk kolom. Aturan yang
        // memakai dimensi lain akan kehilangan syaratnya saat turun versi —
        // konsekuensi yang tak terhindarkan dari kembali ke dua kolom tetap.
        foreach (DB::table('pricing_rule_conditions')->where('dimension', 'monthly_revenue')->get() as $condition) {
            DB::table('pricing_rules')
                ->where('id', $condition->pricing_rule_id)
                ->update([
                    $condition->operator === 'gte' ? 'min_revenue' : 'max_revenue' => $condition->value,
                ]);
        }

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->index(['effective_from', 'min_revenue']);
            $table->dropColumn('priority');
        });

        Schema::dropIfExists('pricing_rule_conditions');
    }
};
