<?php

namespace App\Jobs;

use App\Actions\ApplyRemoteGeneration;
use App\Actions\RejectGeneration;
use App\Enums\GenerationStatus;
use App\ImageProvider\ImageProviderClient;
use App\ImageProvider\ImageProviderException;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a pending generation to the provider, retrying when the provider asks us to wait.
 *
 * Every attempt sends the generation's uuid as the Idempotency-Key, so a retry
 * after a timeout can never create (or bill) a second generation.
 */
final class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Backoff doubles from 4s up to a 60s cap: roughly four minutes in total. */
    public const int MAX_ATTEMPTS = 8;

    public int $tries = self::MAX_ATTEMPTS;

    public function __construct(public readonly int $generationId) {}

    public function handle(ImageProviderClient $provider, ApplyRemoteGeneration $apply, RejectGeneration $reject): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null || $generation->status !== GenerationStatus::Pending) {
            return;
        }

        try {
            $remote = $provider->createGeneration(
                idempotencyKey: $generation->uuid,
                prompt: $generation->prompt,
                size: $generation->size,
                quality: $generation->quality,
                referenceImages: $generation->reference_images,
                metadata: ['generation_id' => $generation->uuid],
            );
        } catch (ImageProviderException $e) {
            if ($e->isRetryable() && $this->attempts() < self::MAX_ATTEMPTS) {
                $this->release($this->backoffSeconds($e));

                return;
            }

            $reject->handle($generation->id, $e->errorCode, $e->getMessage());

            return;
        }

        $apply->handle($remote);
    }

    /**
     * Anything unexpected: stop retrying and tell the user, rather than leave it queued forever.
     */
    public function failed(?Throwable $exception): void
    {
        app(RejectGeneration::class)->handle(
            $this->generationId,
            'internal_error',
            'We could not submit this generation. You were not charged.',
        );
    }

    private function backoffSeconds(ImageProviderException $e): int
    {
        $exponential = min(60, 2 ** ($this->attempts() + 1));

        return max($e->retryAfterSeconds ?? 0, $exponential);
    }
}
