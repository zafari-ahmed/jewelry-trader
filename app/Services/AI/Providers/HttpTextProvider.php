<?php

namespace App\Services\AI\Providers;

use App\Models\Setting;
use App\Services\AI\Contracts\AiTextProvider;
use RuntimeException;

class HttpTextProvider extends HttpChatProvider implements AiTextProvider
{
    public function generate(string $prompt, array $context = []): string
    {
        $this->assertEnabled('ai.description');
        $this->assertConfigured();

        $model = (string) Setting::get('ai.description_model', '');

        if ($model === '') {
            throw new RuntimeException('No description model is configured. Add one in Settings → AI & Automation.');
        }

        $messages = [
            ['role' => 'system', 'content' => trim((string) Setting::get('ai.description_prompt', ''))],
            ['role' => 'user', 'content' => $this->userPrompt($prompt, $context)],
        ];

        $response = $this->send($messages, $model, expectJson: (bool) ($context['expects_json'] ?? false));

        return trim($this->content($response));
    }

    private function userPrompt(string $prompt, array $context): string
    {
        unset($context['expects_json']);

        if ($context === []) {
            return $prompt;
        }

        return $prompt."\n\nVerified attributes of the piece:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
