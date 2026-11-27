<?php

use App\Actions\ApplyRemoteGeneration;
use App\Enums\GenerationStatus;
use App\Events\GenerationUpdated;
use App\ImageProvider\Data\GenerationData;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use App\Pricing\Nanodollars;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Event::fake([GenerationUpdated::class]);
    Queue::fake();
});

function remote(Generation $generation, string $status): GenerationData
{
    return GenerationData::fromArray(providerGeneration($status, [
        'metadata' => ['generation_id' => $generation->uuid],
    ]));
}

it('records the exact cost and price when a generation succeeds', function () {
    $generation = Generation::factory()->status(GenerationStatus::Processing)->create();

    app(ApplyRemoteGeneration::class)->handle(remote($generation, 'succeeded'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost?->toDecimal())->toBe('0.305510000')
        ->and($generation->price?->toDecimal())->toBe('0.381887500')
        ->and($generation->completed_at?->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z');

    Event::assertDispatched(GenerationUpdated::class);
    Queue::assertPushed(StoreGenerationImage::class);
});

it('charges nothing for a failed generation', function () {
    $generation = Generation::factory()->status(GenerationStatus::Processing)->create();

    app(ApplyRemoteGeneration::class)->handle(remote($generation, 'failed'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price?->amount)->toBe(0)
        ->and($generation->provider_cost?->amount)->toBe(0)
        ->and($generation->error_code)->toBe('generation_failed');
});

it('ends in the same state with one charge, whatever order events arrive in', function (array $order) {
    $generation = Generation::factory()->create(); // pending: the create response hasn't returned yet

    foreach ($order as $status) {
        app(ApplyRemoteGeneration::class)->handle(remote($generation, $status));
    }

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->price?->toDecimal())->toBe('0.381887500')
        ->and($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K');

    // The move into "succeeded" (which sets the price) happened exactly once.
    Queue::assertPushed(StoreGenerationImage::class, 1);
})->with(function () {
    $permutations = function (array $items) use (&$permutations): array {
        if (count($items) <= 1) {
            return [$items];
        }

        $result = [];
        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);
            foreach ($permutations(array_values($rest)) as $tail) {
                $result[] = [$item, ...$tail];
            }
        }

        return $result;
    };

    foreach ($permutations(['queued', 'processing', 'succeeded', 'succeeded']) as $order) {
        yield implode(' → ', $order) => [$order];
    }
});

it('never moves a finished generation back', function (string $final, string $late) {
    $generation = Generation::factory()->status(GenerationStatus::Processing)->create();
    $apply = app(ApplyRemoteGeneration::class);

    $apply->handle(remote($generation, $final));
    $before = $generation->refresh()->only(['status', 'price', 'provider_cost', 'completed_at']);

    $apply->handle(remote($generation, $late));

    expect($generation->refresh()->only(['status', 'price', 'provider_cost', 'completed_at']))->toEqual($before);
})->with([
    ['succeeded', 'processing'],
    ['succeeded', 'failed'],
    ['failed', 'succeeded'],
]);

it('trusts the provider over our own rejection', function () {
    $generation = Generation::factory()->create();
    $generation->forceFill(['status' => GenerationStatus::Rejected, 'price' => Nanodollars::zero(), 'error_code' => 'connection_failed'])->save();

    app(ApplyRemoteGeneration::class)->handle(remote($generation, 'succeeded'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->price?->toDecimal())->toBe('0.381887500')
        ->and($generation->error_code)->toBeNull();
});

it('ignores generations that are not ours', function () {
    $result = app(ApplyRemoteGeneration::class)->handle(GenerationData::fromArray(providerGeneration('succeeded', [
        'metadata' => ['generation_id' => 'not-a-uuid'],
    ])));

    expect($result)->toBeNull();
    Event::assertNotDispatched(GenerationUpdated::class);
});
