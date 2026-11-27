<?php

namespace App\Jobs;

use App\Actions\ApplyRemoteGeneration;
use App\ImageProvider\Data\GenerationData;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies a stored webhook event. Runs on the queue so the webhook endpoint can
 * answer within the provider's 10 second limit.
 */
final class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(ApplyRemoteGeneration $apply): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        if (str_starts_with($event->type, 'generation.')) {
            $apply->handle(GenerationData::fromArray($event->payload['data']['object']));
        }

        $event->processed_at = now();
        $event->save();
    }
}
