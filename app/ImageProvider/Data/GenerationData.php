<?php

namespace App\ImageProvider\Data;

use App\Enums\GenerationStatus;
use App\Pricing\Rates;
use App\Pricing\TokenUsage;
use Carbon\CarbonImmutable;

/**
 * The provider's generation object, as returned by the API and carried by webhooks.
 */
final readonly class GenerationData
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public array $metadata,
        public Rates $rates,
        public ?TokenUsage $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?string $errorCode,
        public ?string $errorMessage,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $generation
     */
    public static function fromArray(array $generation): self
    {
        $date = fn (string $key): ?CarbonImmutable => isset($generation[$key])
            ? CarbonImmutable::parse($generation[$key])
            : null;

        return new self(
            id: $generation['id'],
            status: GenerationStatus::from($generation['status']),
            metadata: $generation['metadata'] ?? [],
            rates: Rates::fromArray($generation['rates']),
            usage: isset($generation['usage']) ? TokenUsage::fromArray($generation['usage']) : null,
            imageUrl: $generation['image_url'] ?? null,
            imageUrlExpiresAt: $date('image_url_expires_at'),
            errorCode: $generation['error']['code'] ?? null,
            errorMessage: $generation['error']['message'] ?? null,
            createdAt: CarbonImmutable::parse($generation['created_at']),
            startedAt: $date('started_at'),
            completedAt: $date('completed_at'),
        );
    }

    /**
     * Our own generation id, sent as metadata when we created it. Webhooks can
     * arrive before the create request returns, so this is how we find our record.
     */
    public function localId(): ?string
    {
        return $this->metadata['generation_id'] ?? null;
    }
}
