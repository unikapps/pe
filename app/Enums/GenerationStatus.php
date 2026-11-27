<?php

namespace App\Enums;

/**
 * Where a generation is in its life.
 *
 * The provider's lifecycle is queued → processing → succeeded | failed. We add two
 * states of our own around submission:
 *  - pending:  saved by us, not yet accepted by the provider (e.g. waiting out a 429).
 *  - rejected: we gave up submitting it (content policy, daily quota, retries exhausted).
 */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Rejected = 'rejected';

    /**
     * Statuses only move forward. Events can arrive late or twice, so a change is
     * applied only if it moves the generation further along; anything else is stale.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            // Final: the provider never changes a finished generation.
            self::Succeeded, self::Failed => false,
            // We believed the provider never created it (e.g. the create request timed
            // out and we stopped retrying). If the provider tells us otherwise, it did
            // create it and may bill us, so its word wins.
            self::Rejected => $next->isRemote(),
            // Rejecting means the provider never accepted it, so only before submission.
            default => $next === self::Rejected
                ? $this === self::Pending
                : $next->rank() > $this->rank(),
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Rejected], true);
    }

    /**
     * Whether this is a status the provider itself reports.
     */
    public function isRemote(): bool
    {
        return in_array($this, [self::Queued, self::Processing, self::Succeeded, self::Failed], true);
    }

    /**
     * The status shown to users, one of the four users see. Before the provider
     * accepts a generation it's simply queued; if we couldn't submit it, it failed.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending, self::Queued => 'queued',
            self::Rejected => 'failed',
            default => $this->value,
        };
    }

    private function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed, self::Rejected => 3,
        };
    }
}
