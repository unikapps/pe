<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Events\GenerationUpdated;
use App\ImageProvider\Data\GenerationData;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use App\Pricing\Nanodollars;
use App\Pricing\PriceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The single place that changes a generation's status or cost.
 *
 * Every source of truth about a generation goes through here: the create
 * response, webhooks and the fallback poll. They can arrive twice, late or
 * out of order, so this is written to be safe to call any number of times
 * with any version of the generation:
 *  - the row is locked, so two deliveries can't interleave;
 *  - statuses only move forward, so a stale "processing" can't undo "succeeded";
 *  - the price is set only on the move into "succeeded", which happens once.
 */
final readonly class ApplyRemoteGeneration
{
    public function __construct(private PriceCalculator $calculator) {}

    public function handle(GenerationData $remote): ?Generation
    {
        [$generation, $statusChanged] = DB::transaction(function () use ($remote): array {
            $generation = $this->findLocked($remote);

            if ($generation === null) {
                return [null, false];
            }

            $generation->provider_id ??= $remote->id;
            $generation->rates ??= $remote->rates->toArray();
            $generation->submitted_at ??= $remote->createdAt;

            if (! $generation->status->canTransitionTo($remote->status)) {
                $generation->save();

                return [$generation, false];
            }

            if ($generation->status === GenerationStatus::Rejected) {
                // We gave up on it, but the provider did accept it: undo the rejection.
                $generation->provider_cost = null;
                $generation->price = null;
            }

            $generation->status = $remote->status;
            $generation->started_at ??= $remote->startedAt;
            $generation->error_code = null;
            $generation->error_message = null;

            match ($remote->status) {
                GenerationStatus::Succeeded => $this->recordSuccess($generation, $remote),
                GenerationStatus::Failed => $this->recordFailure($generation, $remote),
                default => null,
            };

            $generation->save();

            return [$generation, true];
        });

        if ($statusChanged) {
            GenerationUpdated::dispatch($generation->user_id, $generation->uuid);

            if ($generation->status === GenerationStatus::Succeeded) {
                StoreGenerationImage::dispatch($generation->id);
            }
        }

        return $generation;
    }

    private function findLocked(GenerationData $remote): ?Generation
    {
        $localId = $remote->localId();

        return Generation::query()
            ->when(
                $localId !== null && Str::isUuid($localId),
                fn ($query) => $query->where('uuid', $localId),
                fn ($query) => $query->where('provider_id', $remote->id),
            )
            ->lockForUpdate()
            ->first();
    }

    private function recordSuccess(Generation $generation, GenerationData $remote): void
    {
        if ($remote->usage === null) {
            throw new LogicException("Succeeded generation [{$remote->id}] has no usage.");
        }

        // Billed at the rates fixed when the generation was created, which
        // the provider sends with it, not today's rates.
        $providerCost = $this->calculator->providerCost($remote->usage, $remote->rates);

        $generation->usage = $remote->usage->toArray();
        $generation->provider_cost = $providerCost;
        $generation->price = $this->calculator->userPrice($providerCost);
        $generation->image_url = $remote->imageUrl;
        $generation->image_url_expires_at = $remote->imageUrlExpiresAt;
        $generation->completed_at = $remote->completedAt;
    }

    private function recordFailure(Generation $generation, GenerationData $remote): void
    {
        // Failed generations are never billed, by the provider or by us.
        $generation->provider_cost = Nanodollars::zero();
        $generation->price = Nanodollars::zero();
        $generation->error_code = $remote->errorCode;
        $generation->error_message = $remote->errorMessage;
        $generation->completed_at = $remote->completedAt;
    }
}
