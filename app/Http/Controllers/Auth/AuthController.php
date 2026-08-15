<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessPresetService;
use App\Services\Pricing\PublicPricing;
use App\Services\SignupGuardService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function showRegister(PublicPricing $pricing, BusinessPresetService $presets)
    {
        return Inertia::render('Auth/Register', [
            // Pilihannya datang dari katalog dimensi harga — satu daftar untuk
            // form ini, panel platform, dan penyusunan aturan harga.
            'businessTypes' => config('pricing-dimensions.business_type.options', []),
            // Masa gratis berakhir dengan perpindahan ke paket berbayar
            // (`[BL-052]`), dan sampai `[BL-071]` itu tidak disebut di satu pun
            // layar sebelum orang menekan "Daftar". Angkanya dibacakan dari
            // config dan `plans` lewat pembaca yang sama dengan halaman harga
            // publik — panjang masa gratis dan penanda paket tujuan keduanya
            // bisa diubah tanpa deploy, jadi menyalinnya ke Vue berarti halaman
            // ini akan berbohong pada hari salah satunya digeser.
            'trial' => $pricing->trialNotice(),
            // Kapabilitas awal yang ditentukan jenis usaha (`[BL-034]`).
            // Katalog dan PETA UTUHNYA dikirim, bukan preset untuk satu jenis
            // usaha saja: daftar centangnya harus ikut berubah begitu pilihan
            // jenis usaha diganti, dan menunggu jawaban server untuk itu berarti
            // formulir yang berkedip di tengah pengisian.
            'featureCatalog' => $presets->catalog(),
            'featurePresets' => $presets->presets(),
            // Keadaan awal daftar centang, untuk pendaftar yang belum menyentuh
            // pilihan jenis usaha sama sekali.
            'defaultFeatures' => $presets->featuresFor(null),
        ]);
    }

    public function register(Request $request, SubscriptionService $subscriptions, SignupGuardService $signupGuard, BusinessPresetService $presets)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            // Ditanyakan sejak awal karena ia dasar penetapan harga, dan
            // menanyakannya belakangan berarti seluruh tenant yang mendaftar
            // lebih dulu tak pernah punya nilainya. Tetap `nullable` supaya
            // pendaftaran tidak dijegal pertanyaan yang jawabannya bisa "belum
            // jelas" — yang kosong jatuh ke bawaan netral, bukan ke `null`, dan
            // pemiliknya bisa memperbaikinya sendiri dari Pengaturan.
            'business_type' => ['nullable', Rule::in(array_keys(config('pricing-dimensions.business_type.options', [])))],
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            // Hasil AKHIR daftar centang, bukan nama presetnya (`[BL-034]`).
            // Bedanya penting: preset cuma mengisi centangnya di layar, dan
            // pendaftar boleh melepas centang mana pun sebelum lanjut —
            // mengirim nama preset berarti pilihan itu diam-diam dibuang di
            // server. `sometimes` supaya klien yang tak mengirimnya sama sekali
            // (uji lama, permintaan langsung) jatuh ke preset, bukan ke tenant
            // tanpa satu pun kapabilitas.
            'features' => 'sometimes|array',
            'features.*' => Rule::in($presets->featureNames()),
        ]);

        $user = DB::transaction(function () use ($validated, $subscriptions, $presets) {
            $slug = Str::slug($validated['business_name']);
            $baseSlug = $slug;
            $suffix = 1;

            while (Tenant::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            // Kunci ini bisa TIDAK ADA sama sekali, bukan sekadar kosong:
            // aturan `nullable` membuat field yang tak dikirim hilang dari
            // hasil validasi.
            $businessType = ($validated['business_type'] ?? null) ?: Tenant::BUSINESS_TYPE_DEFAULT;

            // Daftar KOSONG tetap dihormati — pendaftar yang melepas semua
            // centang memang meminta aplikasi paling polos, dan itu pilihan yang
            // sah. Karena itu pemeriksaannya `array_key_exists`, bukan `?:`
            // yang akan menganggap `[]` sebagai "tidak dijawab" lalu
            // mengembalikan preset yang baru saja ia tolak.
            $features = array_key_exists('features', $validated)
                ? $validated['features']
                : $presets->featuresFor($businessType);

            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'business_type' => $businessType,
                'slug' => $slug,
                'status' => Tenant::STATUS_TRIAL,
                // Inilah satu-satunya tempat preset diterapkan. Ia nilai AWAL,
                // bukan ikatan: jenis usaha bisa diubah kapan saja dari
                // Pengaturan, dan mengubahnya sengaja TIDAK menerapkan ulang
                // preset ini — pemilik yang sudah mematikan antrian dapur tidak
                // boleh mendapatkannya kembali hanya karena ia membetulkan jenis
                // usahanya (`[BL-034]`).
                ...$presets->columnsFor($features),
            ]);

            // Masa coba dibuka di transaksi yang sama dengan pendaftarannya.
            // Kalau langganan gagal dibuat, tenant-nya pun tidak jadi — lebih
            // baik daripada tenant yang hidup tanpa langganan sama sekali dan
            // lolos dari setiap batas.
            $subscriptions->startTrial($tenant);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'owner',
            ]);
        });

        // Di LUAR transaksi: penandaan dan peringatannya mengirim surel, dan
        // kegagalan mengirim surel tidak boleh membatalkan pendaftaran yang
        // sudah sah.
        $signupGuard->record($user->tenant, $request->ip());

        $user->sendEmailVerificationNotification();

        Auth::login($user);
        $request->session()->regenerate();

        // Diarahkan ke halaman verifikasi, bukan ke dashboard: gerbangnya akan
        // mengalihkan ke sana juga, dan mendarat langsung di sana jauh lebih
        // jelas daripada mendarat di dashboard sekejap lalu terlempar.
        return redirect()->route('verification.notice');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // Diperiksa SETELAH kata sandi cocok, bukan sebelumnya: memeriksa
            // lebih dulu akan membuat halaman ini bisa dipakai memastikan
            // sebuah akun ada tanpa mengetahui kata sandinya.
            if (! $user->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun ini dinonaktifkan. Hubungi pemilik usaha Anda.',
                ]);
            }

            $request->session()->regenerate();

            // Redirect berdasarkan role
            if ($user->isOwner()) {
                return redirect()->intended('/owner/dashboard');
            }

            return redirect()->intended('/cashier/pos');
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
