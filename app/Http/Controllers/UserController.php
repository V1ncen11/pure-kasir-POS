<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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
                $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
            )
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->withCount('transactions')
            ->latest()
            ->paginate(15)
            ->withQueryString();

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

        ActivityLog::log('Tambah Pengguna', "Menambahkan pengguna baru: {$user->name} (Role: {$user->role})");

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
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

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'is_active' => $data['is_active'],
            ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
        ]);

        // Sync role
        $user->syncRoles([$data['role']]);

        ActivityLog::log('Edit Pengguna', "Mengubah data pengguna: {$user->name} (Role: {$user->role})");

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $userName = $user->name;
        $user->delete();

        ActivityLog::log('Hapus Pengguna', "Menghapus pengguna: {$userName}");

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
