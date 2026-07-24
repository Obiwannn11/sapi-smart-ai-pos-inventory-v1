<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request, SubscriptionService $subscriptions)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
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

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/owner/dashboard');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Redirect berdasarkan role
            if ($user->isOwner()) {
                return redirect()->intended('/owner/dashboard');
            }

            return redirect()->intended('/cashier/pos');
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
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
