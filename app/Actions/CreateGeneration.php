<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\ImageProvider\ImageProviderClient;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pricing\GenerationSpec;
use App\Pricing\PriceCalculator;

/**
 * Records a user's request and queues it for the provider.
 *
 * Saving first and submitting from a job means rate limits and outages delay a
 * generation instead of losing it, and the user gets an answer straight away.
 */
final readonly class CreateGeneration
{
    public function __construct(
        private ImageProviderClient $provider,
        private PriceCalculator $calculator,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     */
    public function handle(User $user, string $prompt, Size $size, Quality $quality, array $referenceImages): Generation
    {
        $estimate = $this->calculator->estimate(
            new GenerationSpec($prompt, $size, $quality, count($referenceImages)),
            $this->provider->pricing(),
        );

        $generation = $user->generations()->create([
            'status' => GenerationStatus::Pending,
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'estimated_price' => $estimate->expected,
            'estimated_price_max' => $estimate->max,
        ]);

        SubmitGeneration::dispatch($generation->id);

        return $generation;
    }
}
