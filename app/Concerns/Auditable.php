<?php

namespace App\Concerns;

/**
 * Observers are registered centrally in AppServiceProvider::bootAuditing()
 * so the audited model list is visible in one place.
 *
 * Rule 3.5: logging is automatic via a model observer, so a new create/update
 * path cannot forget to call a helper. Models opt in by using this trait.
 *
 * A model may declare:
 *   public array $auditRedacted = ['value'];   // never store these in plaintext
 *   public string $auditCategory = 'financial'; // drives retention (IRS: 7 years)
 */
trait Auditable
{
    public function auditRedactedAttributes(): array
    {
        return property_exists($this, 'auditRedacted') ? $this->auditRedacted : [];
    }

    public function auditCategory(): string
    {
        return property_exists($this, 'auditCategory') ? $this->auditCategory : 'general';
    }

    /** e.g. "setting" -> setting.updated */
    public function auditName(): string
    {
        return str(class_basename($this))->snake()->toString();
    }
}
