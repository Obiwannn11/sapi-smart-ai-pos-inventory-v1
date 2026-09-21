<?php

use App\Models\Tenant;

use function Pest\Laravel\post;

/**
 * `business_type` tetap opsional saat mendaftar — `[BL-079]` opsi (iii).
 *
 * Entri ini meninjau ulang `nullable` setelah kolomnya jadi dimensi harga
 * sungguhan, dan pemilik memutuskan MEMPERTAHANKANNYA (2026-08-24). Alasannya
 * bukan kemalasan: belum ada satu pun aturan di `pricing_rules` yang bersyarat
 * `business_type`, jadi mewajibkannya menaikkan gesekan pendaftaran tanpa
 * menukar apa pun.
 *
 * Yang dikunci di sini adalah kedua sisi keputusan itu sekaligus — bahwa
 * mendaftar tanpa menjawab tetap BOLEH, dan bahwa yang kosong mendarat di
 * bawaan yang **disebutkan di layar**, bukan yang ditebakkan diam-diam.
 * Tanpa berkas ini, "rapikan validasi" berikutnya akan mengubah salah satunya
 * tanpa ada yang menagih.
 */
function registerWithoutBusinessType(array $overrides = []): \Illuminate\Testing\TestResponse
{
    return post('/register', array_merge([
        'business_name' => 'Warung Belum Yakin',
        'name' => 'Pemilik',
        'email' => 'pemilik@belumyakin.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ], $overrides));
}

test('mendaftar tanpa mengisi jenis usaha tetap diterima', function () {
    registerWithoutBusinessType()->assertSessionHasNoErrors();

    expect(Tenant::where('name', 'Warung Belum Yakin')->exists())->toBeTrue();
});

test('jenis usaha yang dikosongkan mendarat di bawaan, bukan null', function () {
    // `null` di kolom ini akan membuat `BusinessTypeResolver` tidak punya nilai
    // untuk dinilai, dan tenant menghilang dari setiap aturan harga yang
    // memakai dimensi ini — termasuk aturan yang seharusnya menguntungkannya.
    registerWithoutBusinessType(['business_type' => '']);

    $tenant = Tenant::where('name', 'Warung Belum Yakin')->firstOrFail();

    expect($tenant->business_type)->toBe(Tenant::BUSINESS_TYPE_DEFAULT)
        ->and($tenant->business_type)->not->toBeNull();
});

test('layar daftar menyebutkan bawaannya, tidak menyembunyikannya', function () {
    // Inti keluhan `[BL-079]`: "Belum ditentukan" tersimpan sebagai `lainnya`
    // tanpa pernah mengatakannya, jadi sebagian tenant bisa masuk kelompok
    // tarif lewat jawaban yang tak pernah mereka berikan. Yang diperiksa di
    // sini bukan susunan kalimatnya, melainkan bahwa pilihan kosongnya
    // menyebut nama bawaan yang benar-benar tersimpan.
    $option = config('pricing-dimensions.business_type.options.'.Tenant::BUSINESS_TYPE_DEFAULT);

    $markup = file_get_contents(resource_path('js/Pages/Auth/Register.vue'));

    preg_match('/<option value="">(.*?)<\/option>/s', $markup, $matches);

    expect($matches)->toHaveKey(1)
        ->and($matches[1])->toContain($option);
});

test('jenis usaha yang tidak dikenal tetap ditolak', function () {
    // Opsional bukan berarti bebas: nilainya masih dasar penetapan harga, dan
    // nilai di luar katalog dimensi tidak akan pernah cocok dengan aturan mana
    // pun — ia hanya akan diam sampai ada yang membandingkan tagihannya.
    registerWithoutBusinessType(['business_type' => 'peternakan-naga'])
        ->assertSessionHasErrors('business_type');

    expect(Tenant::where('name', 'Warung Belum Yakin')->exists())->toBeFalse();
});
