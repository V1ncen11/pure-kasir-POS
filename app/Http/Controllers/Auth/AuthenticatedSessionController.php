<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'sessionKicked' => session('session_kicked'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $userId = Auth::id();
        $currentSessionId = $request->session()->getId();

        // Ambil semua sesi lama user ini (selain sesi yang baru)
        $oldSessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->pluck('id');

        // Tandai setiap sesi lama di Cache agar middleware bisa deteksi
        foreach ($oldSessions as $oldSessionId) {
            Cache::put('session_kicked_' . $oldSessionId, true, now()->addMinutes(10));
        }

        // Hapus semua sesi lama dari database (1 akun = 1 sesi)
        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        $request->session()->regenerate();

        ActivityLog::log('Login', 'Pengguna berhasil masuk ke sistem.');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        ActivityLog::log('Logout', 'Pengguna keluar dari sistem.');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
