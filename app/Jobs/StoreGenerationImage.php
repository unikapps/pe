<?php

namespace App\Jobs;

use App\Events\GenerationUpdated;
use App\ImageProvider\ImageProviderClient;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Copies a finished image to our own storage: the provider's URLs expire after 24 hours.
 */
final class StoreGenerationImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $generationId) {}

    public function handle(ImageProviderClient $provider): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null || $generation->image_url === null || $generation->image_path !== null) {
            return;
        }

        $path = "generations/{$generation->uuid}.png";

        Storage::put($path, $provider->downloadImage($generation->image_url));

        $generation->image_path = $path;
        $generation->save();

        GenerationUpdated::dispatch($generation->user_id, $generation->uuid);
    }
}
