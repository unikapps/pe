<?php

namespace App\Pricing;

use App\Enums\Quality;
use App\Enums\Size;

/**
 * The provider's published rates and token rules, from GET /v1/pricing.
 */
final readonly class PricingTable
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public int $textTokenVarianceBasisPoints,
        public int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {}

    /**
     * @param  array{
     *     rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string},
     *     tokens: array{
     *         characters_per_text_token: int,
     *         text_token_variance: float|int,
     *         per_reference_image: int,
     *         output: array<string, array<string, int>>,
     *         reasoning: array<string, array{min: int, max: int}>,
     *     },
     * }  $pricing
     */
    public static function fromArray(array $pricing): self
    {
        $tokens = $pricing['tokens'];

        return new self(
            rates: Rates::fromArray($pricing['rates']),
            charactersPerTextToken: $tokens['characters_per_text_token'],
            // 0.15 => 1500 basis points, so the token maths below stays in integers.
            textTokenVarianceBasisPoints: (int) round($tokens['text_token_variance'] * 10_000),
            tokensPerReferenceImage: $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoningTokens: $tokens['reasoning'],
        );
    }

    public function outputTokens(Quality $quality, Size $size): int
    {
        return $this->outputTokens[$quality->value][$size->value];
    }

    /**
     * @return array{min: int, max: int}
     */
    public function reasoningTokens(Quality $quality): array
    {
        return $this->reasoningTokens[$quality->value];
    }
}
