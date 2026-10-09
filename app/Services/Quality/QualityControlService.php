<?php

namespace App\Services\Quality;

use App\Models\Product;
use App\Models\QualityControlCheck;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The quality gate: evaluate, score, and decide what may advance.
 *
 * Derived checks are re-read from the record every time, so the gauge cannot
 * drift away from the truth. Anything a person answered — or overrode — is
 * left alone, because a human answer outranks the machine here exactly as it
 * does on the item record.
 */
class QualityControlService
{
    public function __construct(private QualityCheckRegistry $registry) {}

    /**
     * Bring a piece's checks up to date with what the record now says.
     *
     * @return Collection<int, QualityControlCheck>
     */
    public function evaluate(Product $product): Collection
    {
        $product->loadMissing(['images', 'gemstones', 'stock', 'currentPricing', 'transferRequests', 'locks']);

        $existing = QualityControlCheck::where('product_id', $product->id)->get()->keyBy('check_key');

        foreach ($this->registry->all() as $check) {
            $row = $existing->get($check->key);

            // A person's answer stands. Re-deriving over the top of it would
            // quietly undo the judgement the gauge exists to record — unless
            // nobody has answered it yet, in which case applicability may
            // still have changed underneath it.
            $answered = $row && ($row->verified_by !== null || $row->is_override);

            if ($answered) {
                if ($row->check_type !== $check->type) {
                    $row->update(['check_type' => $check->type]);
                }

                continue;
            }

            [$status, $detail] = $check->evaluate($product);

            QualityControlCheck::updateOrCreate(
                ['product_id' => $product->id, 'check_key' => $check->key],
                [
                    'stage' => $check->stage,
                    'check_type' => $check->type,
                    'status' => $status,
                    'detail' => $detail,
                ],
            );
        }

        // A check that was dropped from the registry should not haunt a gauge.
        $known = array_map(fn (QualityCheck $c) => $c->key, $this->registry->all());
        QualityControlCheck::where('product_id', $product->id)->whereNotIn('check_key', $known)->delete();

        return QualityControlCheck::where('product_id', $product->id)->orderBy('check_key')->get();
    }

    /**
     * A person's answer to a check that needs one.
     *
     * Marking it as an override when it contradicts what the system derived
     * is what turns the field blue and puts it in the audit trail.
     */
    public function record(Product $product, string $key, string $status, ?User $user, ?string $notes = null): QualityControlCheck
    {
        $check = $this->registry->find($key);

        $row = QualityControlCheck::firstOrNew([
            'product_id' => $product->id,
            'check_key' => $key,
        ]);

        $contradictsTheRecord = ! $check?->needsAPerson()
            && $row->exists
            && $row->status !== $status
            && ! $row->is_override;

        $row->fill([
            'stage' => $check?->stage ?? $row->stage,
            'check_type' => $check?->type ?? $row->check_type,
            'status' => $status,
            'verified_by' => $user?->id,
            'verified_at' => now(),
            'notes' => $notes,
            'is_override' => $contradictsTheRecord || $row->is_override,
        ])->save();

        return $row->fresh();
    }

    /**
     * The score: passed ÷ applicable × 100, unweighted.
     *
     * Deliberately unweighted, per the agreed design. Weighting the score as
     * well as using criticality to block would let a piece score well while
     * missing something critical — exactly the failure the gauge exists to
     * prevent. Criticality governs blocking; the score counts.
     *
     * @return array{score:int, passed:int, applicable:int, blocked:bool, blockers:Collection}
     */
    public function score(Product $product): array
    {
        $checks = QualityControlCheck::where('product_id', $product->id)->get();

        $applicable = $checks->where('status', '!=', QualityStatus::NOT_APPLICABLE);
        $passed = $applicable->where('status', QualityStatus::PASSED);

        $blockers = $applicable
            ->where('check_type', 'critical')
            ->whereIn('status', [QualityStatus::FAILED, QualityStatus::PENDING]);

        return [
            'score' => $applicable->isEmpty() ? 0 : (int) round($passed->count() / $applicable->count() * 100),
            'passed' => $passed->count(),
            'applicable' => $applicable->count(),
            'blocked' => $blockers->isNotEmpty(),
            'blockers' => $blockers,
        ];
    }

    /**
     * The gauge: one colour per stage, in lifecycle order.
     *
     * @return array<string, array{label:string, colour:string, passed:int, applicable:int}>
     */
    public function gauge(Product $product): array
    {
        $checks = QualityControlCheck::where('product_id', $product->id)->get()->groupBy('stage');
        $gauge = [];

        foreach (QualityCheckRegistry::STAGES as $stage => $label) {
            $forStage = $checks->get($stage, collect());
            $applicable = $forStage->where('status', '!=', QualityStatus::NOT_APPLICABLE);

            $gauge[$stage] = [
                'label' => $label,
                'colour' => QualityStatus::stageColour($forStage),
                'passed' => $applicable->where('status', QualityStatus::PASSED)->count(),
                'applicable' => $applicable->count(),
            ];
        }

        return $gauge;
    }

    /**
     * The facts a customer may be shown, each one attributable.
     *
     * No score and no stars: the number measures record completeness, and a
     * customer would read it as a judgement about the piece. A piece in fair
     * condition with a complete record would score full marks, which is the
     * kind of claim that gets a dealer into trouble. Specific verified facts
     * are both more honest and more persuasive.
     *
     * @return array<int, array{claim:string, detail:?string, by:?string, on:?string}>
     */
    public function publicClaims(Product $product): array
    {
        $rows = QualityControlCheck::where('product_id', $product->id)
            ->where('status', QualityStatus::PASSED)
            ->with('verifiedBy')
            ->get()
            ->keyBy('check_key');

        $claims = [];

        foreach ($this->registry->all() as $check) {
            if ($check->publicClaim === null || ! $rows->has($check->key)) {
                continue;
            }

            $row = $rows->get($check->key);

            $claims[] = [
                'claim' => $check->publicClaim,
                'detail' => $row->detail,
                'by' => $row->verifiedBy?->publicCredit(),
                'on' => $row->verified_at?->format('j F Y'),
            ];
        }

        return $claims;
    }
}
