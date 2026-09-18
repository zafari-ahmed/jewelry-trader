<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the audit trail. Model events arrive through AuditableObserver;
 * non-model events (login, MFA, credential reveal, override) call event().
 */
class AuditLogger
{
    /** Never stored in plaintext, whatever model they appear on. */
    private const ALWAYS_REDACTED = ['password', 'remember_token', 'two_factor_secret'];

    public function model(Model $model, string $verb, array $old, array $new): AuditLog
    {
        $redacted = array_merge(
            self::ALWAYS_REDACTED,
            method_exists($model, 'auditRedactedAttributes') ? $model->auditRedactedAttributes() : [],
        );

        $name = method_exists($model, 'auditName')
            ? $model->auditName()
            : str(class_basename($model))->snake()->toString();

        return $this->write(
            action: "{$name}.{$verb}",
            category: method_exists($model, 'auditCategory') ? $model->auditCategory() : 'general',
            auditableType: $model::class,
            auditableId: $model->getKey(),
            old: $this->redact($old, $redacted),
            new: $this->redact($new, $redacted),
        );
    }

    /** Non-model events: login, mfa.failed, setting.revealed, override.approved. */
    public function event(string $action, array $context = [], string $category = 'general'): AuditLog
    {
        return $this->write($action, $category, null, null, [], $context);
    }

    private function write(string $action, string $category, ?string $auditableType, ?int $auditableId, array $old, array $new): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'category' => $category,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => str(Request::userAgent() ?? '')->limit(500)->toString() ?: null,
        ]);
    }

    /** Encrypted values are masked in the log, never written in the clear. */
    private function redact(array $values, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null) {
                $values[$key] = '••••redacted••••';
            }
        }

        unset($values['created_at'], $values['updated_at']);

        return $values;
    }
}
