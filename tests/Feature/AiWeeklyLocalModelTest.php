<?php

namespace Tests\Feature;

use App\AiWeekly\ModelClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiWeeklyLocalModelTest extends TestCase
{
    public function test_local_completion_does_not_require_or_send_an_api_key(): void
    {
        config(['ai_weekly.model' => 'local-llama', 'ai_weekly.model_url' => 'http://127.0.0.1:11434/v1/chat/completions', 'ai_weekly.model_key' => null]);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"summary":"Local summary"}']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ])]);

        $result = app(ModelClient::class)->complete('Summarize', ['content' => 'Source material']);

        $this->assertSame('Local summary', $result['summary']);
        $this->assertSame(['input_tokens' => 10, 'output_tokens' => 5], $result['_usage']);
        Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:11434/v1/chat/completions'
            && ! $request->hasHeader('Authorization') && $request['model'] === 'local-llama'
            && $request['response_format'] === ['type' => 'json_object']);
    }

    public function test_authenticated_model_servers_still_receive_the_configured_key(): void
    {
        config(['ai_weekly.model' => 'local-llama', 'ai_weekly.model_url' => 'http://127.0.0.1:8081/v1/chat/completions', 'ai_weekly.model_key' => 'local-secret']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

        app(ModelClient::class)->complete('Summarize', []);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer local-secret'));
    }
}
