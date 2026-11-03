<?php

namespace App\Services\Inventory;

use App\Models\GemstoneDetail;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * What has to be said about a stone before the piece can go on sale.
 *
 * Two obligations, and they are enforced differently on purpose.
 *
 * Origin is *rendered*, never written. The client asked for the "Lab-Grown"
 * label to be forced into the public description with no exceptions. Writing
 * it into a text field cannot deliver that: whoever edits the description
 * last can delete it, and a description drafted by hand would never have had
 * it. So the public page builds the disclosure from the record every time it
 * renders. There is no copy to forget and no field to clear — the only way to
 * remove the label is to change what the stone is.
 *
 * Treatment is *blocked*. Nobody can derive whether a ruby was heated, so the
 * system cannot state it; it can only refuse to publish until a person has.
 * Silence about treatment on corundum or beryl is not the same as
 * "untreated", because both are treated as a matter of course.
 */
class StoneDisclosureService
{
    /**
     * The disclosure lines for the public page, derived from the stones.
     *
     * @return string[]
     */
    public function statements(Product $product): array
    {
        $required = $this->lowered('reference.origins_requiring_disclosure', ['lab-grown', 'simulated']);
        $statements = [];

        foreach ($this->stones($product) as $stone) {
            $origin = strtolower(trim((string) $stone->origin));

            if ($origin === '' || ! in_array($origin, $required, true)) {
                continue;
            }

            $label = strtolower(trim((string) $stone->origin));
            $what = strtolower(trim((string) ($stone->stone_type ?: 'stone')));

            // The stone type often already carries the origin — "Lab-Grown
            // Diamond" — and repeating it reads as a stutter rather than as
            // a disclosure.
            $noun = str_contains($what, $label) ? $what : "{$label} {$what}";

            // One line per distinct claim: three lab-grown diamonds in one
            // ring is one disclosure, not three.
            $statements[$label.'|'.$what] = 'This piece contains a '.$noun.'.';
        }

        return array_values($statements);
    }

    /**
     * Why this item cannot be listed yet.
     *
     * @return string[]
     */
    public function blockers(Product $product): array
    {
        $needsTreatment = $this->lowered('reference.treatment_disclosure_required', ['ruby', 'sapphire', 'emerald']);
        $blockers = [];

        foreach ($this->stones($product) as $stone) {
            $type = strtolower(trim((string) $stone->stone_type));

            if ($type === '' || blank($stone->treatment) === false) {
                continue;
            }

            foreach ($needsTreatment as $needle) {
                if ($needle !== '' && str_contains($type, $needle)) {
                    $blockers[$type] = "the {$stone->stone_type} needs its treatment recorded — heated, oiled, untreated or unknown, but not blank";

                    break;
                }
            }
        }

        return array_values($blockers);
    }

    public function isSatisfied(Product $product): bool
    {
        return $this->blockers($product) === [];
    }

    /** @return Collection<int, GemstoneDetail> */
    private function stones(Product $product): Collection
    {
        return $product->relationLoaded('gemstones')
            ? $product->gemstones
            : $product->gemstones()->get();
    }

    /** @return string[] */
    private function lowered(string $key, array $fallback): array
    {
        $value = Setting::get($key, $fallback);

        if (! is_array($value) || $value === []) {
            $value = $fallback;
        }

        return array_map(fn ($v) => strtolower(trim((string) $v)), $value);
    }
}
