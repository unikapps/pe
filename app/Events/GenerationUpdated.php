<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the owner's open pages that a generation changed, so Inertia live props
 * reload it from the server.
 *
 * It's only a signal: the payload carries no prices or statuses, the reload does.
 * Dispatched after commit, so the reload never reads the state from before the change.
 */
final class GenerationUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly string $generationId,
    ) {}

    public static function channelFor(int $userId): PrivateChannel
    {
        return new PrivateChannel("users.{$userId}");
    }

    public function broadcastOn(): PrivateChannel
    {
        return self::channelFor($this->userId);
    }

    public function broadcastAs(): string
    {
        return 'generation.updated';
    }

    /**
     * @return array{generation_id: string}
     */
    public function broadcastWith(): array
    {
        return ['generation_id' => $this->generationId];
    }
}
