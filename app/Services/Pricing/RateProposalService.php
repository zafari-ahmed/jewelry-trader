<?php

namespace App\Services\Pricing;

use App\Models\RateChangeProposal;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The AI proposes; the appraiser approves.
 *
 * This is the one place in the system where a single judgement could move
 * every price at once, so it is the one place that is deliberately slow. A
 * proposal sits in a queue until a person decides on it, and the appraiser can
 * clear a batch in one action without losing the veto on any row.
 */
class RateProposalService
{
    /** A proposal outside this band is refused outright rather than queued. */
    private const MIN_MULTIPLIER = 0.1;

    private const MAX_MULTIPLIER = 10.0;

    /**
     * Record a batch of proposals. Nothing is applied.
     *
     * @param  array<int, array{table_key:string, entry_key:string, proposed_value:float, reason?:string, confidence?:int, source?:string}>  $proposals
     * @return string the batch id
     */
    public function propose(array $proposals, string $source = 'AI'): string
    {
        $batchId = (string) Str::uuid();

        foreach ($proposals as $proposal) {
            $tableKey = $proposal['table_key'];

            if (! array_key_exists($tableKey, RateChangeProposal::TABLES)) {
                throw new RuntimeException("{$tableKey} is not a rate table that can be proposed against.");
            }

            $value = (float) $proposal['proposed_value'];

            // A proposal that could not be a sane rate is a malfunction, not a
            // suggestion. It never reaches the queue, so nobody has to spend
            // attention rejecting it.
            if ($value < self::MIN_MULTIPLIER || $value > self::MAX_MULTIPLIER) {
                continue;
            }

            $current = Setting::get($tableKey, []);

            RateChangeProposal::create([
                'batch_id' => $batchId,
                'table_key' => $tableKey,
                'entry_key' => $proposal['entry_key'],
                'value_at_proposal' => is_array($current)
                    ? RateTable::exact($current, $proposal['entry_key'])?->value
                    : null,
                'proposed_value' => $value,
                'reason' => $proposal['reason'] ?? null,
                'confidence' => $proposal['confidence'] ?? null,
                'source' => $proposal['source'] ?? $source,
                'status' => 'pending',
            ]);
        }

        return $batchId;
    }

    /**
     * Apply the given proposals to the rate tables.
     *
     * @param  int[]  $ids
     * @return array{applied:int, stale:int}
     */
    public function approve(array $ids, ?int $userId, ?string $note = null): array
    {
        $applied = 0;
        $stale = 0;

        DB::transaction(function () use ($ids, $userId, $note, &$applied, &$stale) {
            $proposals = RateChangeProposal::whereIn('id', $ids)->pending()->get();

            // Group by table so a batch touching eight rows of one table is
            // one write to that setting, not eight.
            foreach ($proposals->groupBy('table_key') as $tableKey => $group) {
                $table = Setting::get($tableKey, []);
                $table = is_array($table) ? $table : [];
                $changed = false;

                foreach ($group as $proposal) {
                    // Somebody edited this rate after the proposal was made.
                    // Their decision is newer and was made with their eyes
                    // open, so it stands and the proposal goes back for a
                    // fresh look.
                    if ($proposal->isStale()) {
                        $proposal->update([
                            'status' => 'stale',
                            'decided_by' => $userId,
                            'decided_at' => now(),
                            'decision_note' => 'The rate changed after this was proposed.',
                        ]);
                        $stale++;

                        continue;
                    }

                    // The approved figure carries its confidence and source
                    // into the table, so the provenance of a rate survives
                    // the decision rather than living only in the queue.
                    $table = RateTable::write(
                        $table,
                        $proposal->entry_key,
                        $proposal->proposed_value,
                        $proposal->confidence,
                        $proposal->source,
                    );
                    $changed = true;

                    $proposal->update([
                        'status' => 'approved',
                        'decided_by' => $userId,
                        'decided_at' => now(),
                        'decision_note' => $note,
                    ]);
                    $applied++;
                }

                if ($changed) {
                    // One audited write per table (rule 3.5), so the log reads
                    // as the change the appraiser actually made.
                    Setting::set($tableKey, $table, $userId);
                }
            }
        });

        return ['applied' => $applied, 'stale' => $stale];
    }

    /** @param int[] $ids */
    public function reject(array $ids, ?int $userId, ?string $note = null): int
    {
        return RateChangeProposal::whereIn('id', $ids)->pending()->update([
            'status' => 'rejected',
            'decided_by' => $userId,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }
}
