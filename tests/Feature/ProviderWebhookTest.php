<?php

use App\Enums\GenerationStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.image_provider.webhook_secret' => 'whsec_test']);
});

function postWebhook(array $event, ?string $signature = null): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);

    // call() sends the raw body but ignores withHeader(), so the signature goes
    // in as a server variable: "X-Provider-Signature" -> HTTP_X_PROVIDER_SIGNATURE.
    $header = 'HTTP_'.strtoupper(str_replace('-', '_', (string) config('services.image_provider.signature_header')));

    return test()->call('POST', route('webhooks.provider'), server: [
        'CONTENT_TYPE' => 'application/json',
        $header => $signature ?? providerSignature($body, 'whsec_test'),
    ], content: $body);
}

function succeededEvent(Generation $generation, string $eventId = 'evt_1'): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => 'generation.succeeded',
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => ['object' => providerGeneration('succeeded', [
            'metadata' => ['generation_id' => $generation->uuid],
        ])],
    ];
}

it('rejects requests with a bad signature', function () {
    Queue::fake();
    $generation = Generation::factory()->status(GenerationStatus::Processing)->create();

    postWebhook(succeededEvent($generation), signature: 't='.time().',v1=forged')->assertStatus(400);

    expect(WebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('stores the event and processes it on the queue', function () {
    Queue::fake();
    $generation = Generation::factory()->status(GenerationStatus::Processing)->create();

    postWebhook(succeededEvent($generation))->assertNoContent();

    expect(WebhookEvent::sole()->event_id)->toBe('evt_1');
    Queue::assertPushed(ProcessWebhookEvent::class);
});

it('records a twice-delivered event once', function () {
    Storage::fake();
    Http::fake(['provider.example.test/images/*' => Http::response('png-bytes')]);
    $generation = Generation::factory()->status(GenerationStatus::Processing)->create();

    // The queue runs synchronously in tests, so each delivery is fully processed.
    postWebhook(succeededEvent($generation))->assertNoContent();
    postWebhook(succeededEvent($generation))->assertNoContent();

    $generation->refresh();
    expect(WebhookEvent::count())->toBe(1)
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->price?->toDecimal())->toBe('0.381887500')
        ->and($generation->image_path)->toBe("generations/{$generation->uuid}.png");
    Storage::assertExists("generations/{$generation->uuid}.png");
});

it('acknowledges test pings', function () {
    postWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => now()->toIso8601String(), 'data' => []])
        ->assertNoContent();

    expect(WebhookEvent::sole()->processed_at)->not->toBeNull();
});
