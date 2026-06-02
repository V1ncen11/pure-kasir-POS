<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'activity',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a new activity log entry and auto-purge entries older than 30 days.
     */
    public static function log(string $activity, ?string $description = null): void
    {
        // 1. Save the new log entry
        self::create([
            'user_id'    => auth()->id(),
            'activity'   => $activity,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // 2. Auto-purge logs older than 30 days to keep the table lean
        self::where('created_at', '<', now()->subDays(30))->delete();
    }
}
