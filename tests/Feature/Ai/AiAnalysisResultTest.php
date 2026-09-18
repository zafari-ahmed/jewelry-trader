<?php

namespace Tests\Feature\Ai;

use App\Models\AiAnalysisRecord;
use App\Models\AiCorrection;
use App\Services\AI\AiAnalysisResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiAnalysisResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_carries_what_persists_regardless_of_provider(): void
    {
        $result = AiAnalysisResult::make(
            rawResponse: ['stone' => 'diamond'],
            confidenceScore: 91,
            reasoningSummary: 'Old European cut, rose-cut surround.',
            modelUsed: 'fixture-model',
        );

        $this->assertSame(['stone' => 'diamond'], $result->rawResponse);
        $this->assertSame(91, $result->confidenceScore);
        $this->assertSame('fixture-model', $result->modelUsed);
        $this->assertNotNull($result->capturedAt);
    }

    public function test_confidence_outside_zero_to_one_hundred_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AiAnalysisResult::make(['x' => 1], 101, 'summary', 'fixture-model');
    }

    public function test_a_result_persists_against_a_product(): void
    {
        AiAnalysisResult::make(['stone' => 'ruby'], 72, 'Burma ruby, unheated.', 'fixture-model')
            ->persistFor(productId: 4412, analysisType: 'gemstone');

        $record = AiAnalysisRecord::query()->firstOrFail();

        $this->assertSame(4412, $record->product_id);
        $this->assertSame('gemstone', $record->analysis_type);
        $this->assertSame(72, $record->confidence_score);
        $this->assertSame(['stone' => 'ruby'], $record->raw_response);
    }

    public function test_the_correction_log_is_ready_for_phase_two_training_signal(): void
    {
        $this->assertTrue(Schema::hasTable('ai_correction_log'));

        AiCorrection::create([
            'product_id' => 4412,
            'field_name' => 'style_period',
            'original_value' => 'Victorian',
            'final_value' => 'Edwardian',
            'reason' => 'Hallmark dates the piece to 1905.',
        ]);

        $this->assertDatabaseHas('ai_correction_log', ['field_name' => 'style_period', 'final_value' => 'Edwardian']);
    }
}
