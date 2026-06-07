<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSessionWithNotification
{
    /**
     * Handle an incoming request.
     * Deteksi apakah user ini sudah di-kick dari perangkat lain.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah sesi ini sudah ditandai sebagai "di-kick"
        // Tandanya disimpan di cache dengan key = session ID lama
        if ($request->hasSession()) {
            $sessionId = $request->session()->getId();
            $cacheKey  = 'session_kicked_' . $sessionId;

            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);

                // Bersihkan sesi dan redirect ke login dengan notifikasi
                $request->session()->flush();

                return redirect()->route('login')
                    ->with('session_kicked', true);
            }
        }

        return $next($request);
    }
}
