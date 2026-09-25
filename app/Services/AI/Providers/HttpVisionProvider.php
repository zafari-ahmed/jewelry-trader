<?php

namespace App\Services\AI\Providers;

use App\Models\Setting;
use App\Services\AI\AiAnalysisResult;
use App\Services\AI\Contracts\AiVisionProvider;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Analyses intake photographs and returns catalogue attributes.
 *
 * What comes back is a *suggestion*, never a decision: every value lands on
 * the record as a yellow field for a human to accept, edit or reject.
 */
class HttpVisionProvider extends HttpChatProvider implements AiVisionProvider
{
    /** What the model is asked to return, by analysis type. */
    private const SHAPES = [
        'classification' => ['category', 'subcategory', 'title', 'object_type'],
        'style' => ['style_period', 'era_confidence', 'design_notes'],
        'material' => ['metal_type', 'metal_purity', 'weight_estimate_grams'],
        'gemstone' => ['gemstones'],
        'hallmark' => ['hallmark_text', 'maker', 'assay_notes'],
        'condition' => ['condition_grade', 'condition_notes'],
        'full' => [
            'title', 'category', 'subcategory', 'style_period', 'metal_type',
            'measurements', 'hallmark_text', 'brand', 'condition_grade',
            'condition_notes', 'gemstones', 'weight_estimate_grams',
        ],
    ];

    public function analyze(array $imagePaths, string $analysisType): AiAnalysisResult
    {
        $this->assertEnabled('ai.vision');
        $this->assertConfigured();

        if ($imagePaths === []) {
            throw new RuntimeException('At least one photograph is needed before the images can be analysed.');
        }

        $model = (string) Setting::get('ai.vision_model', '');

        if ($model === '') {
            throw new RuntimeException('No photo-analysis model is configured. Add one in Settings → AI & Automation.');
        }

        $response = $this->send([
            ['role' => 'system', 'content' => $this->systemPrompt($analysisType)],
            ['role' => 'user', 'content' => $this->userContent($imagePaths)],
        ], $model);

        $decoded = $this->decode($this->content($response));

        if ($decoded === null) {
            throw new RuntimeException('The AI service replied in a form this system could not read.');
        }

        $confidence = (int) round((float) ($decoded['confidence'] ?? 0));
        unset($decoded['confidence']);

        $reasoning = (string) ($decoded['reasoning'] ?? '');
        unset($decoded['reasoning']);

        return AiAnalysisResult::make(
            rawResponse: $decoded,
            confidenceScore: max(0, min(100, $confidence)),
            reasoningSummary: $reasoning,
            modelUsed: $this->modelUsed($response, $model),
        );
    }

    private function systemPrompt(string $analysisType): string
    {
        $fields = implode(', ', self::SHAPES[$analysisType] ?? self::SHAPES['full']);

        return trim((string) Setting::get('ai.vision_prompt', ''))."\n\n".
            "Return a single JSON object with these keys where the photographs support them: {$fields}. ".
            'Add "confidence" (0-100) for how certain you are overall, and "reasoning" — one sentence on what the images showed. '.
            'Use "gemstones" as an array of objects with stone_type, shape, cut, color and estimated_weight_ct. '.
            'Omit any key you cannot judge from the images. Never guess a maker, a hallmark or a carat weight that is not visible.';
    }

    /** @param string[] $imagePaths */
    private function userContent(array $imagePaths): array
    {
        $content = [['type' => 'text', 'text' => 'Catalogue the piece in these photographs.']];

        foreach (array_slice($imagePaths, 0, 8) as $path) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $this->dataUri($path)]];
        }

        return $content;
    }

    /** Images are sent inline, so the photographs never need a public URL. */
    private function dataUri(string $path): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException("Photograph [{$path}] could not be read.");
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
