<?php

namespace App\Pricing;

/**
 * Token counts for one generation, by kind of token.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
    ) {}

    /**
     * @param  array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}  $usage
     */
    public static function fromArray(array $usage): self
    {
        return new self(
            inputTextTokens: $usage['input_text_tokens'],
            inputImageTokens: $usage['input_image_tokens'],
            outputTokens: $usage['output_tokens'],
            reasoningTokens: $usage['reasoning_tokens'],
        );
    }

    /**
     * @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
