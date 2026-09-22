<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FieldColorRule;
use Illuminate\Database\Seeder;

/**
 * Sensible defaults only — every rule is editable in Settings → Field Rules.
 *
 * red    = required, blocks submission (enforced server-side)
 * yellow = normally AI-suggested; a plain editable field in Phase 1
 * gray   = not applicable to this category
 * green  = computed once a valid value is entered
 * blue   = a human replaced a suggested value
 */
class FieldColorRuleSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rings', 'slug' => 'rings', 'sort_order' => 1],
            ['name' => 'Necklaces', 'slug' => 'necklaces', 'sort_order' => 2],
            ['name' => 'Bracelets', 'slug' => 'bracelets', 'sort_order' => 3],
            ['name' => 'Brooches', 'slug' => 'brooches', 'sort_order' => 4],
            ['name' => 'Earrings', 'slug' => 'earrings', 'sort_order' => 5],
            ['name' => 'Watches', 'slug' => 'watches', 'sort_order' => 6],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(['slug' => $category['slug']], $category);
        }

        // [field, color, required, section, label, help, sort]
        $defaults = [
            ['title', 'red', true, 'identity', 'Item title', null, 10],
            ['sku', 'red', true, 'identity', 'SKU', null, 20],
            ['category', 'red', true, 'identity', 'Category', 'Drives which fields apply to this item.', 30],
            ['subcategory', 'green', false, 'identity', 'Subcategory', null, 40],
            ['brand', 'yellow', false, 'identity', 'Brand / maker', null, 50],
            ['style_period', 'yellow', false, 'identity', 'Style period', null, 60],

            ['metal_type', 'red', true, 'materials', 'Metal type & purity', null, 70],
            ['weight_grams', 'green', false, 'materials', 'Weight (grams)', null, 80],
            ['measurements', 'red', true, 'materials', 'Measurements', null, 90],
            ['hallmark_text', 'yellow', false, 'materials', 'Maker / hallmark', null, 100],

            ['condition_notes', 'red', true, 'condition', 'Condition & restoration notes', 'Appraisal records cannot be generated without condition notes.', 110],
            ['internal_description', 'gray', false, 'condition', 'Internal notes', 'Never shown to customers.', 120],

            ['customer_description', 'green', false, 'copy', 'Customer description', null, 130],
            ['seo_description', 'gray', false, 'copy', 'SEO description', null, 140],
            ['marketplace_description', 'gray', false, 'copy', 'Marketplace description', null, 150],
            ['social_description', 'gray', false, 'copy', 'Social description', null, 160],

            ['acquisition_value', 'red', true, 'pricing', 'Acquisition cost', null, 170],
            ['retail_price', 'red', true, 'pricing', 'Retail price', null, 180],
            ['insurance_value', 'green', false, 'pricing', 'Insurance value', null, 190],
            ['negotiation_min', 'green', false, 'pricing', 'Negotiation floor', null, 200],

            ['location_id', 'red', true, 'placement', 'Location', null, 210],

            // Category-specific fields: not applicable by default.
            ['ring_size', 'gray', false, 'materials', 'Ring size', null, 95],
            ['chain_length', 'gray', false, 'materials', 'Chain length', null, 96],
        ];

        foreach ($defaults as [$field, $color, $required, $section, $label, $help, $sort]) {
            FieldColorRule::query()->updateOrCreate(
                ['model' => 'App\\Models\\Product', 'field_name' => $field, 'category' => null],
                compact('color', 'section', 'label', 'help') + ['is_required' => $required, 'sort_order' => $sort],
            );
        }

        // Category overrides: a ring needs a size, a brooch does not.
        $overrides = [
            ['rings', 'ring_size', 'red', true],
            ['necklaces', 'chain_length', 'red', true],
            ['necklaces', 'ring_size', 'gray', false],
            ['bracelets', 'chain_length', 'red', true],
            ['brooches', 'ring_size', 'gray', false],
            ['watches', 'movement_type', 'red', true],
        ];

        foreach ($overrides as [$category, $field, $color, $required]) {
            FieldColorRule::query()->updateOrCreate(
                ['model' => 'App\\Models\\Product', 'field_name' => $field, 'category' => $category],
                [
                    'color' => $color,
                    'is_required' => $required,
                    'section' => 'materials',
                    'label' => str($field)->headline()->toString(),
                    'sort_order' => 97,
                ],
            );
        }

        FieldColorRule::flushCache();
    }
}
