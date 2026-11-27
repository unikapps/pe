<?php

namespace App\ImageProvider;

use App\Enums\Quality;
use App\Enums\Size;
use App\ImageProvider\Data\GenerationData;
use App\Pricing\PricingTable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The only code that talks HTTP to the provider. Callers get typed data back, or a
 * ImageProviderException; retrying is the caller's decision (see SubmitGeneration).
 */
final readonly class ImageProviderClient
{
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    public function createGeneration(
        string $idempotencyKey,
        string $prompt,
        Size $size,
        Quality $quality,
        array $referenceImages,
        array $metadata,
    ): GenerationData {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('generations', array_filter([
                'prompt' => $prompt,
                'size' => $size->value,
                'quality' => $quality->value,
                'reference_images' => $referenceImages,
                'metadata' => $metadata,
            ], fn (mixed $value) => $value !== [])));

        return GenerationData::fromArray($response->json());
    }

    public function getGeneration(string $id): GenerationData
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($id)));

        return GenerationData::fromArray($response->json());
    }

    /**
     * Current rates and token rules. Public and rarely changing, so cached briefly.
     * Generations are billed at the rates returned on the generation itself, so a
     * stale cache can only affect estimates, never recorded costs.
     */
    public function pricing(): PricingTable
    {
        /** @var array{rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}, tokens: array{characters_per_text_token: int, text_token_variance: float|int, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}} $pricing */
        $pricing = Cache::remember('image_provider.pricing', now()->addMinutes(5), fn () => $this
            ->send(fn (PendingRequest $http) => $http->get('pricing'))
            ->json());

        return PricingTable::fromArray($pricing);
    }

    /**
     * What the provider billed this API key, per UTC day (the day each generation completed).
     *
     * @return list<array{date: string, generations: int, cost_usd: string}>
     */
    public function usage(string $startDate, string $endDate): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        return $response->json('data');
    }

    /**
     * The PNG bytes behind a signed image URL. The URL carries its own signature,
     * so the API key isn't sent with it.
     */
    public function downloadImage(string $imageUrl): string
    {
        try {
            return Http::connectTimeout(5)->timeout(30)->get($imageUrl)->throw()->body();
        } catch (ConnectionException $e) {
            throw new ImageProviderException('connection_failed', $e->getMessage());
        }
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        $http = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);

        if ($this->apiKey !== null) {
            $http = $http->withToken($this->apiKey);
        }

        try {
            $response = $request($http);
        } catch (ConnectionException $e) {
            // We can't know whether the provider received the request. Retrying with
            // the same Idempotency-Key is safe either way.
            throw new ImageProviderException('connection_failed', $e->getMessage());
        }

        if ($response->failed()) {
            throw new ImageProviderException(
                errorCode: $response->json('error.code') ?? 'http_'.$response->status(),
                message: $response->json('error.message') ?? $response->reason(),
                httpStatus: $response->status(),
                retryAfterSeconds: is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null,
            );
        }

        return $response;
    }
}
