<?php

namespace App\Http\Controllers;

use App\ImageProvider\WebhookSignature;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives provider webhooks: verify, store once, answer fast, process on the queue.
 */
class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSignature $signature): Response
    {
        // Verify the raw body, before any JSON decoding.
        if (! $signature->isValid($request->getContent(), $request->header((string) config('services.image_provider.signature_header')), time())) {
            return response('Invalid signature.', 400);
        }

        /** @var array{id?: string, type?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?: [];

        if (! isset($payload['id'], $payload['type'])) {
            return response('Not a provider event.', 422);
        }

        // The unique event_id makes a repeated delivery find the existing row.
        $event = WebhookEvent::query()->createOrFirst(
            ['event_id' => $payload['id']],
            ['type' => $payload['type'], 'payload' => $payload],
        );

        // Also re-dispatch an event whose processing never finished, so
        // The provider's own retries double as ours. Processing is idempotent.
        if ($event->processed_at === null) {
            ProcessWebhookEvent::dispatch($event->id);
        }

        return response()->noContent();
    }
}
