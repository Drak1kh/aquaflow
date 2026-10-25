<?php

namespace App;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(Model $subject, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        $sensitive = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
        AuditLog::create([
            'water_provider_id' => $subject->water_provider_id ?? null,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'old_values' => $oldValues ? array_diff_key($oldValues, array_flip($sensitive)) : null,
            'new_values' => array_diff_key($newValues ?? $subject->getAttributes(), array_flip($sensitive)),
            'ip_address' => request()->ip(),
        ]);
    }
}
