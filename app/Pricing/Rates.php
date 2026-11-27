<?php

namespace App\Pricing;

/**
 * Prices per 1M tokens, by kind of token.
 *
 * A generation is billed at the rates in force when it was created, which
 * the provider returns on the generation itself.
 */
final readonly class Rates
{
    public function __construct(
        public Nanodollars $inputTextPerMillion,
        public Nanodollars $inputImagePerMillion,
        public Nanodollars $outputPerMillion,
        public Nanodollars $reasoningPerMillion,
    ) {}

    /**
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     */
    public static function fromArray(array $rates): self
    {
        return new self(
            inputTextPerMillion: Nanodollars::fromDecimal($rates['input_text_tokens']),
            inputImagePerMillion: Nanodollars::fromDecimal($rates['input_image_tokens']),
            outputPerMillion: Nanodollars::fromDecimal($rates['output_tokens']),
            reasoningPerMillion: Nanodollars::fromDecimal($rates['reasoning_tokens']),
        );
    }

    /**
     * @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextPerMillion->toDecimal(),
            'input_image_tokens' => $this->inputImagePerMillion->toDecimal(),
            'output_tokens' => $this->outputPerMillion->toDecimal(),
            'reasoning_tokens' => $this->reasoningPerMillion->toDecimal(),
        ];
    }
}
