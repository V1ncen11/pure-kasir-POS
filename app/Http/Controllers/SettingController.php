<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::getAllAsArray(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name'     => 'required|string|max:100',
            'store_address'  => 'nullable|string|max:300',
            'store_phone'    => 'nullable|string|max:30',
            'store_email'    => 'nullable|email|max:100',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'receipt_header' => 'nullable|string|max:200',
            'receipt_footer' => 'nullable|string|max:200',
            'low_stock_alert'=> 'boolean',
            'receipt_paper_width' => 'required|string|in:58mm,80mm',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        ActivityLog::log('Edit Pengaturan', 'Mengubah pengaturan toko.');

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        $old = Setting::get('store_logo');
        if ($old) Storage::disk('public')->delete($old);

        $path = $request->file('logo')->store('settings', 'public');
        Setting::set('store_logo', $path);

        ActivityLog::log('Upload Logo', 'Mengganti logo toko.');

        return back()->with('success', 'Logo berhasil diupload.');
    }
}
