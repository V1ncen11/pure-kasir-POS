<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::when($request->search, fn($q, $s) =>
                $q->where(fn ($search) => $search
                    ->where('name', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%"))
            )
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->withCount('transactions')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $users->getCollection()->transform(function (User $user): User {
            $user->setAttribute('avatar_url', $user->avatar ? '/storage/' . $user->avatar : null);
            return $user;
        });

        return Inertia::render('Users/Index', [
            'users'   => $users,
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'role'     => 'required|in:admin,kasir',
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'password'  => Hash::make($data['password']),
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);
        $user->sendEmailVerificationNotification();

        ActivityLog::log('Tambah Pengguna', "Menambahkan pengguna baru: {$user->name} (Role: {$user->role}). Email verifikasi dikirim.");

        return back()->with('success', "Pengguna berhasil ditambahkan. Tautan verifikasi dikirim ke {$user->email}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'role'      => 'required|in:admin,kasir',
            'is_active' => 'boolean',
            'password'  => ['nullable', Password::min(8)],
        ]);

        $emailChanged = $user->email !== $data['email'];
        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            'role'      => $data['role'],
            'is_active' => $data['is_active'] ?? $user->is_active,
            ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
        ]);

        // Sync role
        $user->syncRoles([$data['role']]);
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        ActivityLog::log('Edit Pengguna', "Mengubah data pengguna: {$user->name} (Role: {$user->role})");

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        if ($user->transactions()->exists() || Purchase::where('user_id', $user->id)->exists()) {
            return back()->with('error', "Pengguna '{$user->name}' memiliki riwayat transaksi atau pembelian. Nonaktifkan akun agar riwayat tetap tersimpan.");
        }

        $userName = $user->name;
        $user->delete();

        ActivityLog::log('Hapus Pengguna', "Menghapus pengguna: {$userName}");

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
