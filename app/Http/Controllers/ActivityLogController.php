<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = ActivityLog::with('user:id,name,role')
            ->when($request->search, fn($q, $s) =>
                $q->where('activity', 'like', "%$s%")
                  ->orWhere('description', 'like', "%$s%")
            )
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn($log) => [
                'id'          => $log->id,
                'activity'    => $log->activity,
                'description' => $log->description,
                'ip_address'  => $log->ip_address,
                'user_agent'  => $log->user_agent,
                'created_at'  => $log->created_at->format('d/m/Y H:i:s'),
                'user'        => $log->user ? [
                    'name' => $log->user->name,
                    'role' => $log->user->role,
                ] : ['name' => 'Sistem', 'role' => '-'],
            ]);

        $users = User::orderBy('name')->get(['id', 'name', 'role']);

        return Inertia::render('ActivityLogs/Index', [
            'logs'    => $logs,
            'users'   => $users,
            'filters' => $request->only(['search', 'user_id', 'date_from', 'date_to']),
        ]);
    }
}
