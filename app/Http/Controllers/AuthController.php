<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // ---- Login (email + password) ----
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    // ---- Registrasi admin baru (email + password) ----
    public function showRegister()
    {
        return view('admin.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan login.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard')
            ->with('success', 'Akun berhasil dibuat. Selamat datang, ' . $user->name . '!');
    }

    // ---- Google OAuth (Laravel Socialite) ----
    public function redirectToGoogle()
    {
        if (! $this->googleConfigured()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Login Google belum dikonfigurasi. Hubungi administrator.']);
        }

        return $this->googleDriver()->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        if (! $this->googleConfigured()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Login Google belum dikonfigurasi.']);
        }

        try {
            $googleUser = $this->googleDriver()->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Gagal login dengan Google. Coba lagi.']);
        }

        $googleEmail = $googleUser->getEmail();
        if (empty($googleEmail)) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Google tidak memberikan alamat email. Tidak bisa melanjutkan.']);
        }

        // Cocokkan dulu via google_id (paling kuat).
        $user = User::where('google_id', $googleUser->getId())->first();

        // Auto-link ke akun email yang sudah ada HANYA jika Google memastikan
        // email-nya terverifikasi — cegah account-takeover lewat email tak
        // terverifikasi. Google mengembalikan flag 'email_verified' pada userinfo.
        if (! $user) {
            $emailVerified = (bool) ($googleUser->user['email_verified'] ?? false);
            $existing = User::where('email', $googleEmail)->first();

            if ($existing && ! $emailVerified) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Email Google ini belum terverifikasi oleh Google sehingga tidak dapat ditautkan ke akun yang sudah ada. Login dengan password Anda.']);
            }

            $user = $existing;
        }

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?: $user->avatar,
            ])->save();
        } else {
            // Pembuatan akun baru — tangani race condition unique(email).
            try {
                $user = User::create([
                    'name' => $googleUser->getName() ?: ($googleUser->getNickname() ?: 'Admin'),
                    'email' => $googleEmail,
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => null, // login khusus Google
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Request paralel sudah membuat akun dengan email/google_id ini.
                $user = User::where('google_id', $googleUser->getId())
                    ->orWhere('email', $googleEmail)
                    ->first();

                if (! $user) {
                    return redirect()->route('login')
                        ->withErrors(['email' => 'Gagal membuat akun Google. Coba lagi.']);
                }
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard')
            ->with('success', 'Berhasil masuk dengan Google.');
    }

    protected function googleConfigured(): bool
    {
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'));
    }

    protected function googleDriver()
    {
        $driver = Socialite::driver('google');

        // Jika GOOGLE_REDIRECT_URI tidak diisi di .env, pakai route callback.
        if (empty(config('services.google.redirect'))) {
            $driver->redirectUrl(route('auth.google.callback'));
        }

        return $driver;
    }
}
