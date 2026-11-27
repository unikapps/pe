<?php

namespace App\Pricing;

/**
 * What a generation will cost the user before it runs.
 *
 * Text and reasoning token counts are only known afterwards, so the price is a
 * range. "max" is a guaranteed upper bound: the actual price never exceeds it.
 */
final readonly class Estimate
{
    public function __construct(
        public Nanodollars $min,
        public Nanodollars $expected,
        public Nanodollars $max,
    ) {}

    /**
     * @return array{min: string, expected: string, max: string}
     */
    public function toArray(): array
    {
        return [
            'min' => $this->min->toDecimal(),
            'expected' => $this->expected->toDecimal(),
            'max' => $this->max->toDecimal(),
        ];
    }
}
