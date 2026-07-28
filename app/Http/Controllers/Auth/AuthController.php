<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
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

    public function showRegister()
    {
        return Inertia::render('Auth/Register', [
            // Pilihannya datang dari katalog dimensi harga — satu daftar untuk
            // form ini, panel platform, dan penyusunan aturan harga.
            'businessTypes' => config('pricing-dimensions.business_type.options', []),
        ]);
    }

    public function register(Request $request, SubscriptionService $subscriptions, SignupGuardService $signupGuard)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            // Ditanyakan sejak awal karena ia dasar penetapan harga, dan
            // menanyakannya belakangan berarti seluruh tenant yang mendaftar
            // lebih dulu tak pernah punya nilainya. `nullable` agar pendaftaran
            // tidak dijegal oleh pertanyaan yang jawabannya bisa "belum jelas" —
            // aturan harga yang menyebut dimensi ini cukup tidak cocok untuk
            // mereka, dan itu perilaku yang benar.
            'business_type' => ['nullable', Rule::in(array_keys(config('pricing-dimensions.business_type.options', [])))],
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = DB::transaction(function () use ($validated, $subscriptions) {
            $slug = Str::slug($validated['business_name']);
            $baseSlug = $slug;
            $suffix = 1;

            while (Tenant::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'business_type' => $validated['business_type'] ?? null,
                'slug' => $slug,
                'status' => Tenant::STATUS_TRIAL,
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
