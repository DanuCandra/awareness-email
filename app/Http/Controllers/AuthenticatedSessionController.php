<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login', ['pageTitle' => 'Masuk']);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $identifier = $request->validated('identifier');
        $key = 'admin-login:'.hash('sha256', $identifier.'|'.$request->ip());
        $ipKey = 'admin-login-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            throw ValidationException::withMessages(['identifier' => 'Terlalu banyak percobaan masuk. Coba kembali dalam satu menit.']);
        }

        $field = str_contains($identifier, '@') ? 'email' : 'username';

        if (! Auth::attempt([$field => $identifier, 'password' => $request->validated('password')])) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);
            throw ValidationException::withMessages(['identifier' => 'Email/username atau password tidak sesuai.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
