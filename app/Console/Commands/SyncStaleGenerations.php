<?php

namespace App\Console\Commands;

use App\Actions\ApplyRemoteGeneration;
use App\Enums\GenerationStatus;
use App\ImageProvider\ImageProviderClient;
use App\ImageProvider\ImageProviderException;
use App\Models\Generation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Webhook delivery isn't guaranteed. This asks the provider directly about
 * generations we haven't heard about for a while, through the same
 * ApplyRemoteGeneration path the webhooks use.
 */
#[Signature('generations:sync-stale {--limit=10 : Most generations to check per run}')]
#[Description('Poll the provider for generations whose webhooks are overdue')]
class SyncStaleGenerations extends Command
{
    /** Generations finish within ~30s ([slow] ones ~90s): 3 quiet minutes means a webhook is overdue. */
    private const int STALE_AFTER_MINUTES = 3;

    public function handle(ImageProviderClient $provider, ApplyRemoteGeneration $apply): int
    {
        $stale = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($stale as $generation) {
            try {
                $remote = $provider->getGeneration((string) $generation->provider_id);
            } catch (ImageProviderException $e) {
                $this->warn("{$generation->uuid}: {$e->errorCode}");

                if ($e->isRetryable()) {
                    break; // Rate limited or overloaded: try again next minute.
                }

                continue;
            }

            $apply->handle($remote);
            // Push it to the back of the queue even if nothing changed.
            $generation->touch();
        }

        return self::SUCCESS;
    }
}
