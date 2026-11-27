<?php

namespace App\Pricing;

use App\Enums\Quality;
use App\Enums\Size;

/**
 * What the user asked for: everything that affects the price before a generation runs.
 */
final readonly class GenerationSpec
{
    public function __construct(
        public string $prompt,
        public Size $size,
        public Quality $quality,
        public int $referenceImageCount,
    ) {}

    /**
     * Prompt length in Unicode characters (code points), which is what the provider counts.
     * JavaScript's String.length counts UTF-16 units instead, so an emoji would count twice there.
     */
    public function promptCharacters(): int
    {
        return mb_strlen($this->prompt, 'UTF-8');
    }
}
