<?php

use App\Actions\ApplyRemoteGeneration;
use App\Actions\RejectGeneration;
use App\Enums\GenerationStatus;
use App\ImageProvider\ImageProviderClient;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.image_provider.api_key' => 'pf_test_key']);
});

it('submits with our uuid as the idempotency key and metadata', function () {
    $generation = Generation::factory()->create();
    Http::fake(['*/v1/generations' => Http::response(providerGeneration('queued', [
        'metadata' => ['generation_id' => $generation->uuid],
    ]), 202)]);

    dispatch_sync(new SubmitGeneration($generation->id));

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->uuid)
        && $request->hasHeader('Authorization', 'Bearer pf_test_key')
        && $request['metadata'] === ['generation_id' => $generation->uuid]
        && ! isset($request['reference_images']));
});

it('waits and retries when the provider is overloaded or rate limited', function (int $status, string $code, int $retryAfter) {
    $generation = Generation::factory()->create();
    Http::fake(['*/v1/generations' => Http::response(
        ['error' => ['type' => 'rate_limit_error', 'code' => $code, 'message' => 'Slow down.']],
        $status,
        ['Retry-After' => (string) $retryAfter],
    )]);

    $job = (new SubmitGeneration($generation->id))->withFakeQueueInteractions();
    $job->handle(app(ImageProviderClient::class), app(ApplyRemoteGeneration::class), app(RejectGeneration::class));

    $job->assertReleased(delay: max($retryAfter, 4));
    expect($generation->refresh()->status)->toBe(GenerationStatus::Pending);
})->with([
    'overloaded' => [503, 'overloaded', 2],
    'concurrency limit' => [429, 'concurrency_limit_exceeded', 5],
    'rate limit' => [429, 'rate_limit_exceeded', 30],
]);

it('rejects a generation the provider refuses, at no cost', function () {
    $generation = Generation::factory()->create();
    Http::fake(['*/v1/generations' => Http::response(
        ['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']],
        400,
    )]);

    dispatch_sync(new SubmitGeneration($generation->id));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Rejected)
        ->and($generation->status->label())->toBe('failed')
        ->and($generation->price?->amount)->toBe(0)
        ->and($generation->error_message)->toBe('The prompt was rejected.');
});
