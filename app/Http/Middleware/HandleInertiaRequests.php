<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'         => $request->user()->id,
                    'name'       => $request->user()->name,
                    'email'      => $request->user()->email,
                    'role'       => $request->user()->role,
                    'is_active'  => $request->user()->is_active,
                    'avatar_url' => $request->user()->avatar
                        ? '/storage/' . $request->user()->avatar
                        : null,
                ] : null,
            ],
            'settings' => fn () => Setting::getAllAsArray(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'lowStockCount' => fn () => $request->user()
                ? Product::where('is_active', true)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->count()
                : 0,
        ];
    }
}
