<?php

namespace App\Services\Security;

use App\Models\Override;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use RuntimeException;

/**
 * Overrides are deliberately high-friction: a reason is required, and the
 * override takes effect only after someone with approve-overrides approves it.
 *
 * A Super Admin is not exempt — requesting and approving are separate steps
 * even when the same person does both, and both are logged.
 */
class OverrideService
{
    public function __construct(private AuditLogger $audit) {}

    public function request(string $type, string $reason, User $by, array $context = []): Override
    {
        if (! array_key_exists($type, Override::TYPES)) {
            throw new RuntimeException("Unknown override type [{$type}].");
        }

        if (trim($reason) === '') {
            // Module 9 acceptance: no override without a reason.
            throw new RuntimeException('An override needs a reason. It appears in the audit log and the commission calculation.');
        }

        $override = Override::create([
            'override_type' => $type,
            'reason' => trim($reason),
            'requested_by' => $by->id,
            'status' => 'pending',
            'order_id' => $context['order_id'] ?? null,
            'product_id' => $context['product_id'] ?? null,
            'amount' => $context['amount'] ?? null,
        ]);

        $this->audit->event('override.requested', [
            'override_id' => $override->id,
            'type' => $type,
            'reason' => $override->reason,
        ], 'financial');

        return $override;
    }

    public function approve(Override $override, User $approver, ?string $note = null): Override
    {
        if (! $approver->can('approve-overrides')) {
            throw new RuntimeException('You do not have authority to approve an override.');
        }

        if ($override->status !== 'pending') {
            throw new RuntimeException('Only a pending override can be approved.');
        }

        $override->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'decision_note' => $note,
        ]);

        $this->audit->event('override.approved', [
            'override_id' => $override->id,
            'type' => $override->override_type,
            // Recorded even when requester and approver are the same person.
            'self_approved' => $override->requested_by === $approver->id,
        ], 'financial');

        return $override->fresh();
    }

    public function reject(Override $override, User $approver, ?string $note = null): Override
    {
        if (! $approver->can('approve-overrides')) {
            throw new RuntimeException('You do not have authority to decide an override.');
        }

        $override->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'decision_note' => $note,
        ]);

        $this->audit->event('override.rejected', ['override_id' => $override->id], 'financial');

        return $override->fresh();
    }

    /** Mark an approved override as having been acted on. */
    public function markApplied(Override $override): Override
    {
        if (! $override->isEffective()) {
            throw new RuntimeException('That override has not been approved.');
        }

        $override->update(['status' => 'applied']);

        return $override->fresh();
    }
}
