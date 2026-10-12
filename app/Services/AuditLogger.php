<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Historique des actions sensibles (corrections de stock, prix, annulations…). */
class AuditLogger
{
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null, ?int $storeId = null): ActivityLog
    {
        $user ??= auth()->user();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'store_id' => $storeId ?? $subject?->getAttribute('store_id') ?? $user?->store_id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
