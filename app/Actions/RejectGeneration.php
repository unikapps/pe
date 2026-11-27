<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Events\GenerationUpdated;
use App\Models\Generation;
use App\Pricing\Nanodollars;
use Illuminate\Support\Facades\DB;

/**
 * Gives up on a generation the provider never accepted. It costs the user nothing.
 */
final class RejectGeneration
{
    public function handle(int $generationId, string $errorCode, string $errorMessage): void
    {
        $rejected = DB::transaction(function () use ($generationId, $errorCode, $errorMessage): ?Generation {
            $generation = Generation::query()->lockForUpdate()->find($generationId);

            // A webhook may already have told us the provider accepted it.
            if ($generation === null || ! $generation->status->canTransitionTo(GenerationStatus::Rejected)) {
                return null;
            }

            $generation->status = GenerationStatus::Rejected;
            $generation->provider_cost = Nanodollars::zero();
            $generation->price = Nanodollars::zero();
            $generation->error_code = $errorCode;
            $generation->error_message = $errorMessage;
            $generation->save();

            return $generation;
        });

        if ($rejected !== null) {
            GenerationUpdated::dispatch($rejected->user_id, $rejected->uuid);
        }
    }
}
