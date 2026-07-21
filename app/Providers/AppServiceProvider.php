<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // --- Gerbang laju endpoint login (BL-007) ---------------------------
        // Kunci per email + IP, bukan IP saja: satu warung/kantor sering keluar
        // lewat satu IP publik, jadi pembatasan per-IP murni membuat kasir
        // saling mengunci padahal tak ada yang menyerang.

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(self::loginThrottleKey($request))
                ->response(self::loginThrottleResponse(...));
        });

        // Panel platform dijaga lebih ketat: satu akun yang jebol membuka data
        // administratif SELURUH klien, bukan satu tenant. Batas per menit sama
        // longgarnya agar pemilik tak terkunci karena salah ketik, tapi ada
        // langit-langit per jam per IP untuk menahan penebakan lintas email —
        // pola yang lolos dari kunci email+IP. Aman di sini karena penggunanya
        // segelintir, sementara di login tenant hal yang sama akan mengunci
        // seluruh kasir di balik satu IP.
        RateLimiter::for('platform-login', function (Request $request) {
            return [
                Limit::perMinute(5)
                    ->by(self::loginThrottleKey($request))
                    ->response(self::loginThrottleResponse(...)),
                Limit::perHour(20)
                    ->by('platform-ip|'.$request->ip())
                    ->response(self::loginThrottleResponse(...)),
            ];
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            return Limit::perMinute(5)->by(self::loginThrottleKey($request));
        });

        // Owner is super-admin within a tenant: bypass every permission check.
        // Returning null lets non-owners fall through to normal gate evaluation.
        Gate::before(function (User $user, string $ability) {
            return $user->isOwner() ? true : null;
        });
    }

    /**
     * Kunci throttle login: email yang dicoba + IP asal.
     *
     * Email dinormalkan (lowercase + transliterasi) agar "Budi@X.com" dan
     * "budi@x.com" dihitung sebagai percobaan yang sama — kalau tidak, penyerang
     * cukup mengubah kapitalisasi untuk mendapat jatah baru.
     */
    private static function loginThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }

    /**
     * Balasan saat batas terlampaui, untuk login berbasis web (Inertia).
     *
     * Dikembalikan sebagai error validasi, bukan halaman 429: alur ini memakai
     * Inertia, dan pesan yang muncul di bawah kolom email jauh lebih berguna
     * bagi pengguna daripada layar galat generik.
     *
     * @param  array<string, mixed>  $headers
     */
    private static function loginThrottleResponse(Request $request, array $headers): RedirectResponse
    {
        $seconds = (int) ($headers['Retry-After'] ?? 60);

        return back()
            ->withErrors(['email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik."])
            ->withHeaders($headers);
    }
}
