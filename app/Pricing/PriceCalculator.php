<?php

namespace App\Pricing;

use LogicException;

/**
 * All money maths in one place: what the provider charges us, and what we charge users.
 */
final class PriceCalculator
{
    /** Users pay the provider's cost plus 25%. */
    public const int MARKUP_PERCENT = 125;

    private const int TOKENS_PER_RATE_UNIT = 1_000_000;

    private const int BASIS_POINTS = 10_000;

    /**
     * Exactly what the provider bills for a finished generation.
     *
     * Rates are per 1M tokens, so a rate of $12.50 is 12_500_000_000 nanodollars
     * per million, and each token costs 12_500 nanodollars. With rates of up to
     * three decimals every cost is a whole number of nanodollars, which matches
     * the provider's "exact, nine decimal places" amounts.
     */
    public function providerCost(TokenUsage $usage, Rates $rates): Nanodollars
    {
        $perMillion = $usage->inputTextTokens * $rates->inputTextPerMillion->amount
            + $usage->inputImageTokens * $rates->inputImagePerMillion->amount
            + $usage->outputTokens * $rates->outputPerMillion->amount
            + $usage->reasoningTokens * $rates->reasoningPerMillion->amount;

        if ($perMillion % self::TOKENS_PER_RATE_UNIT !== 0) {
            // Would need more than nine decimals, which the provider never reports.
            // Fail loudly rather than record a cost that can't match its usage report.
            throw new LogicException('Provider cost is not a whole number of nanodollars; check the rates.');
        }

        return new Nanodollars(intdiv($perMillion, self::TOKENS_PER_RATE_UNIT));
    }

    /**
     * What the user pays for a given provider cost: cost plus markup, with any
     * fraction of a nanodollar rounded up. This is the only place we round.
     */
    public function userPrice(Nanodollars $providerCost): Nanodollars
    {
        return $providerCost->percentRoundedUp(self::MARKUP_PERCENT);
    }

    public function estimate(GenerationSpec $spec, PricingTable $table): Estimate
    {
        $textTokens = intdiv($spec->promptCharacters() + $table->charactersPerTextToken - 1, $table->charactersPerTextToken);
        $variance = $table->textTokenVarianceBasisPoints;
        $reasoning = $table->reasoningTokens($spec->quality);

        $usage = fn (int $text, int $reasoningTokens): TokenUsage => new TokenUsage(
            inputTextTokens: $text,
            inputImageTokens: $spec->referenceImageCount * $table->tokensPerReferenceImage,
            outputTokens: $table->outputTokens($spec->quality, $spec->size),
            reasoningTokens: $reasoningTokens,
        );

        $price = fn (TokenUsage $usage): Nanodollars => $this->userPrice($this->providerCost($usage, $table->rates));

        return new Estimate(
            min: $price($usage(
                intdiv($textTokens * (self::BASIS_POINTS - $variance), self::BASIS_POINTS),
                $reasoning['min'],
            )),
            expected: $price($usage(
                $textTokens,
                intdiv($reasoning['min'] + $reasoning['max'], 2),
            )),
            max: $price($usage(
                self::ceilDiv($textTokens * (self::BASIS_POINTS + $variance), self::BASIS_POINTS),
                $reasoning['max'],
            )),
        );
    }

    private static function ceilDiv(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
