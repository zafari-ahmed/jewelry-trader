<?php

namespace App\Services\AI\Providers;

use App\Exceptions\FeatureNotEnabledException;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared transport for AI capabilities.
 *
 * Deliberately vendor-neutral: it speaks the widely adopted chat-completions
 * shape, and the endpoint, key and model are settings. Pointing the system at
 * a different service — hosted or self-hosted — is a form submission, not a
 * code change. No provider is named anywhere in this codebase.
 */
abstract class HttpChatProvider
{
    protected function endpoint(): string
    {
        $endpoint = trim((string) Setting::get('ai.endpoint', ''));

        if ($endpoint === '') {
            throw new RuntimeException('No AI endpoint is configured. Add one in Settings → AI & Automation.');
        }

        return rtrim($endpoint, '/').'/chat/completions';
    }

    protected function apiKey(): string
    {
        $key = (string) Setting::get('ai.api_key', '');

        if ($key === '') {
            throw new RuntimeException('No AI API key is configured. Add one in Settings → AI & Automation.');
        }

        return $key;
    }

    /** Fail on configuration before doing any work — reading photographs included. */
    protected function assertConfigured(): void
    {
        $this->endpoint();
        $this->apiKey();
    }

    protected function assertEnabled(string $capability): void
    {
        if (! Setting::enabled('ai.enabled') || ! Setting::enabled($capability)) {
            throw FeatureNotEnabledException::for($capability);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    protected function send(array $messages, string $model, bool $expectJson = true): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => (int) Setting::get('ai.max_output_tokens', 1200),
            'temperature' => 0.2,
        ];

        if ($expectJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($this->apiKey())
            ->timeout((int) Setting::get('ai.timeout_seconds', 45))
            ->retry(2, 400, throw: false)
            ->acceptJson()
            ->post($this->endpoint(), $payload);

        if ($response->failed()) {
            throw new RuntimeException($this->errorFrom($response));
        }

        return $response->json() ?? [];
    }

    /** The assistant's message content, whatever wrapper the service uses. */
    protected function content(array $response): string
    {
        return (string) data_get($response, 'choices.0.message.content', '');
    }

    protected function modelUsed(array $response, string $fallback): string
    {
        return (string) ($response['model'] ?? $fallback);
    }

    /**
     * Parse the JSON body of a reply, tolerating a service that wraps it in
     * prose or a fenced block. Returns null when nothing usable came back —
     * callers treat that as "no suggestion", never as a silent success.
     */
    protected function decode(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function errorFrom(Response $response): string
    {
        $message = data_get($response->json(), 'error.message');

        return $message
            ? "The AI service refused the request: {$message}"
            : "The AI service returned status {$response->status()}.";
    }
}
