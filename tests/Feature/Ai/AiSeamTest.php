<?php

namespace Tests\Feature\Ai;

use App\Exceptions\FeatureNotEnabledException;
use App\Models\AiProvider;
use App\Models\Setting;
use App\Services\AI\AiAnalysisResult;
use App\Services\AI\Contracts\AiSearchProvider;
use App\Services\AI\Contracts\AiTextProvider;
use App\Services\AI\Contracts\AiVisionProvider;
use App\Services\AI\Providers\NullAiSearchProvider;
use App\Services\AI\Providers\NullAiTextProvider;
use App\Services\AI\Providers\NullAiVisionProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeVisionProvider;
use Tests\TestCase;

class AiSeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_contracts_have_the_specified_signatures(): void
    {
        $vision = new \ReflectionMethod(AiVisionProvider::class, 'analyze');
        $this->assertSame(['imagePaths', 'analysisType'], array_map(fn ($p) => $p->getName(), $vision->getParameters()));
        $this->assertSame(AiAnalysisResult::class, $vision->getReturnType()->getName());

        $text = new \ReflectionMethod(AiTextProvider::class, 'generate');
        $this->assertSame(['prompt', 'context'], array_map(fn ($p) => $p->getName(), $text->getParameters()));
        $this->assertSame('string', $text->getReturnType()->getName());

        $search = new \ReflectionMethod(AiSearchProvider::class, 'search');
        $this->assertSame(['naturalLanguageQuery'], array_map(fn ($p) => $p->getName(), $search->getParameters()));
        $this->assertSame(\Illuminate\Support\Collection::class, $search->getReturnType()->getName());
    }

    public function test_phase_one_binds_the_null_providers(): void
    {
        $this->assertInstanceOf(NullAiVisionProvider::class, app(AiVisionProvider::class));
        $this->assertInstanceOf(NullAiTextProvider::class, app(AiTextProvider::class));
        $this->assertInstanceOf(NullAiSearchProvider::class, app(AiSearchProvider::class));
    }

    public function test_every_null_provider_refuses_to_run(): void
    {
        $this->expectException(FeatureNotEnabledException::class);

        app(AiVisionProvider::class)->analyze(['front.jpg'], 'classification');
    }

    public function test_the_null_text_provider_refuses_to_run(): void
    {
        $this->expectException(FeatureNotEnabledException::class);

        app(AiTextProvider::class)->generate('anything');
    }

    public function test_the_null_search_provider_refuses_to_run(): void
    {
        $this->expectException(FeatureNotEnabledException::class);

        app(AiSearchProvider::class)->search('edwardian ring under 5k');
    }

    public function test_a_capability_flag_alone_does_not_activate_a_provider(): void
    {
        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);

        // No provider row is configured, so the seam still refuses.
        $this->assertInstanceOf(NullAiVisionProvider::class, app(AiVisionProvider::class));
    }

    public function test_a_configured_provider_row_swaps_the_implementation_without_a_deploy(): void
    {
        AiProvider::query()->create([
            'name' => 'Fixture', 'slug' => 'fixture',
            'driver_class' => FakeVisionProvider::class,
            'is_active' => true, 'capabilities' => ['vision'],
        ]);

        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);
        Setting::set('ai.provider', 'fixture');

        $this->assertInstanceOf(FakeVisionProvider::class, app(AiVisionProvider::class));
    }

    public function test_a_provider_row_whose_class_is_missing_falls_back_to_null(): void
    {
        AiProvider::query()->create([
            'name' => 'Broken', 'slug' => 'broken',
            'driver_class' => 'App\\Services\\AI\\Providers\\DoesNotExist',
            'is_active' => true, 'capabilities' => ['vision'],
        ]);

        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);
        Setting::set('ai.provider', 'broken');

        $this->assertInstanceOf(NullAiVisionProvider::class, app(AiVisionProvider::class));
    }
}
